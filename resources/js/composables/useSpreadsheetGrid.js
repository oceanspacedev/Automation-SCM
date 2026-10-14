import { nextTick, onMounted, onUnmounted, reactive, ref, watch } from 'vue';

const ISO_DATE = /^(\d{4})-(\d{2})-(\d{2})$/;
const DMY_DATE = /^(\d{1,2})\/(\d{1,2})\/(\d{4})$/;

const padLike = (number, sample) => String(number).padStart(sample.length, '0');

/**
 * Nilai sel ke-`step` di bawah saat fill handle ditarik, seperti di Excel:
 * tanggal maju per hari, teks/angka yang diakhiri angka bertambah 1 (nol di depan tetap), selain itu disalin.
 */
export function fillValue(value, step) {
  const text = value === null || value === undefined ? '' : String(value).trim();

  const iso = text.match(ISO_DATE);
  if (iso) {
    return new Date(Date.UTC(+iso[1], +iso[2] - 1, +iso[3] + step)).toISOString().slice(0, 10);
  }

  const dmy = text.match(DMY_DATE);
  if (dmy) {
    const date = new Date(Date.UTC(+dmy[3], +dmy[2] - 1, +dmy[1] + step));
    return `${padLike(date.getUTCDate(), dmy[1])}/${padLike(date.getUTCMonth() + 1, dmy[2])}/${date.getUTCFullYear()}`;
  }

  const numbered = text.match(/^(.*?)(\d+)$/);
  if (!numbered) {
    return value;
  }

  const [, prefix, digits] = numbered;
  const next = (BigInt(digits) + BigInt(step)).toString();

  return prefix + (digits.startsWith('0') ? next.padStart(digits.length, '0') : next);
}

/**
 * Sel tabel yang bisa dipilih, diisi (klik dua kali / langsung ketik) dan ditarik ke bawah seperti spreadsheet.
 * Sel ditandai dengan `data-field` pada <td> dan `data-row-index` pada <tr>; kotak pilihan, editor dan
 * fill handle digambar di atas tabel, di dalam `container` (elemen yang di-scroll, harus `position: relative`).
 *
 * @param {object} options
 * @param {import('vue').Ref<HTMLElement|null>} options.container
 * @param {import('vue').Ref<object[]>} options.rows
 * @param {(field: string, changes: {row: object, value: any}[]) => any} options.save
 * @param {string[]} [options.copyFields] Kolom yang selalu disalin apa adanya saat ditarik (mis. nominal)
 * @param {(field: string, value: any) => string} [options.toEditText]
 * @param {(field: string, text: string) => any} [options.fromEditText]
 */
export function useSpreadsheetGrid({
  container,
  rows,
  save,
  copyFields = [],
  toEditText = (field, value) => (value === null || value === undefined ? '' : String(value)),
  fromEditText = (field, text) => (text.trim() === '' ? null : text.trim()),
}) {
  const activeCell = ref(null);
  const cellEditor = reactive({ open: false, text: '', initialText: '' });
  const cellEditorInput = ref(null);
  const selectionBox = ref(null);
  const fillBox = ref(null);
  const fillTo = ref(null);

  const cellAt = (rowIndex, field) =>
    container.value?.querySelector(`tr[data-row-index="${rowIndex}"] > td[data-field="${field}"]`) ?? null;

  const boxOf = (first, last = first) => {
    const area = container.value.getBoundingClientRect();
    const top = first.getBoundingClientRect();

    return {
      top: top.top - area.top + container.value.scrollTop,
      left: top.left - area.left + container.value.scrollLeft,
      width: top.width,
      height: last.getBoundingClientRect().bottom - top.top,
    };
  };

  const boxStyle = (box) => ({
    top: `${box.top}px`,
    left: `${box.left}px`,
    width: `${box.width}px`,
    height: `${box.height}px`,
  });

  function refreshBoxes() {
    const cell = activeCell.value && cellAt(activeCell.value.rowIndex, activeCell.value.field);
    selectionBox.value = cell ? boxOf(cell) : null;

    const lastFilled = cell && fillTo.value !== null ? cellAt(fillTo.value, activeCell.value.field) : null;
    fillBox.value = lastFilled ? boxOf(cell, lastFilled) : null;
  }

  function select(cell) {
    activeCell.value = cell;
    nextTick(() => {
      cellAt(cell.rowIndex, cell.field)?.scrollIntoView({ block: 'nearest', inline: 'nearest' });
      refreshBoxes();
    });
  }

  function cellFromEvent(event) {
    const td = event.target.closest('td[data-field]');
    const tr = td?.closest('tr[data-row-index]');

    return td && tr ? { rowIndex: Number(tr.dataset.rowIndex), field: td.dataset.field } : null;
  }

  function move(rowStep, columnStep) {
    if (!activeCell.value) return;

    const { rowIndex, field } = activeCell.value;
    const tr = container.value?.querySelector(`tr[data-row-index="${rowIndex}"]`);
    const fields = tr ? [...tr.querySelectorAll(':scope > td[data-field]')].map((td) => td.dataset.field) : [];
    const columnIndex = Math.min(Math.max(fields.indexOf(field) + columnStep, 0), fields.length - 1);

    select({
      rowIndex: Math.min(Math.max(rowIndex + rowStep, 0), rows.value.length - 1),
      field: fields[columnIndex] ?? field,
    });
  }

  function startEdit(cell, typedText) {
    const text = toEditText(cell.field, rows.value[cell.rowIndex]?.[cell.field]);
    cellEditor.initialText = text;
    cellEditor.text = typedText ?? text;
    cellEditor.open = true;
    select(cell);
    nextTick(() => {
      cellEditorInput.value?.focus();
      if (typedText === undefined) cellEditorInput.value?.select();
    });
  }

  function commitEdit() {
    if (!cellEditor.open) return;
    cellEditor.open = false;

    const row = rows.value[activeCell.value?.rowIndex];
    if (row && cellEditor.text !== cellEditor.initialText) {
      save(activeCell.value.field, [{ row, value: fromEditText(activeCell.value.field, cellEditor.text) }]);
    }
  }

  function cancelEdit() {
    cellEditor.open = false;
  }

  function onEditorKeydown(event) {
    if (event.key === 'Enter') {
      event.preventDefault();
      commitEdit();
      move(1, 0);
    } else if (event.key === 'Tab') {
      event.preventDefault();
      commitEdit();
      move(0, event.shiftKey ? -1 : 1);
    } else if (event.key === 'Escape') {
      event.preventDefault();
      cancelEdit();
    }
  }

  function onGridClick(event) {
    if (event.target.closest('a, button, input')) return;

    const cell = cellFromEvent(event);
    if (cell) {
      select(cell);
    } else {
      activeCell.value = null;
      refreshBoxes();
    }
  }

  /**
   * Buka editor untuk sel yang diklik dua kali; false bila yang diklik bukan sel yang bisa diisi.
   */
  function onGridDblClick(event) {
    const cell = cellFromEvent(event);
    if (!cell) return false;

    startEdit(cell);

    return true;
  }

  function onFillMove(event) {
    const area = container.value.getBoundingClientRect();
    if (event.clientY > area.bottom - 24) container.value.scrollTop += 16;
    else if (event.clientY < area.top + 48) container.value.scrollTop -= 16;

    const tr = document.elementFromPoint(event.clientX, event.clientY)?.closest('tr[data-row-index]');
    if (tr && container.value.contains(tr)) {
      fillTo.value = Math.max(activeCell.value.rowIndex, Number(tr.dataset.rowIndex));
      refreshBoxes();
    }
  }

  function onFillEnd(event) {
    window.removeEventListener('mousemove', onFillMove);
    window.removeEventListener('mouseup', onFillEnd);
    document.body.style.userSelect = '';

    const to = fillTo.value;
    fillTo.value = null;
    refreshBoxes();

    if (!activeCell.value || to === null || to <= activeCell.value.rowIndex) return;

    const { rowIndex: from, field } = activeCell.value;

    // Ctrl/Cmd saat dilepas = salin apa adanya tanpa menambah angka
    const source = rows.value[from][field];
    const copy = event.ctrlKey || event.metaKey || copyFields.includes(field);
    const changes = [];
    for (let step = 1; step <= to - from; step++) {
      changes.push({ row: rows.value[from + step], value: copy ? source : fillValue(source, step) });
    }

    save(field, changes);
  }

  function startFill() {
    if (!activeCell.value) return;

    fillTo.value = activeCell.value.rowIndex;
    document.body.style.userSelect = 'none';
    window.addEventListener('mousemove', onFillMove);
    window.addEventListener('mouseup', onFillEnd);
  }

  function onKeydown(event) {
    if (!activeCell.value || cellEditor.open || fillTo.value !== null) return;
    if (document.activeElement?.matches('input, textarea, select, [contenteditable="true"]')) return;

    const arrows = { ArrowUp: [-1, 0], ArrowDown: [1, 0], ArrowLeft: [0, -1], ArrowRight: [0, 1] };

    if (arrows[event.key]) {
      event.preventDefault();
      move(...arrows[event.key]);
    } else if (event.key === 'Enter' || event.key === 'F2') {
      event.preventDefault();
      startEdit(activeCell.value);
    } else if (event.key === 'Escape') {
      activeCell.value = null;
      refreshBoxes();
    } else if (event.key.length === 1 && !event.ctrlKey && !event.metaKey && !event.altKey) {
      // Langsung mengetik di sel terpilih menimpa isinya, seperti di Excel
      event.preventDefault();
      startEdit(activeCell.value, event.key);
    }
  }

  // Data baru dimuat (ganti halaman/filter/auto refresh): isian yang sedang diketik tetap disimpan, pilihan lama dilepas
  watch(rows, (newRows, oldRows) => {
    const row = activeCell.value && oldRows?.[activeCell.value.rowIndex];
    if (cellEditor.open && row && cellEditor.text !== cellEditor.initialText) {
      save(activeCell.value.field, [{ row, value: fromEditText(activeCell.value.field, cellEditor.text) }]);
    }

    activeCell.value = null;
    cellEditor.open = false;
    nextTick(refreshBoxes);
  });

  onMounted(() => {
    window.addEventListener('keydown', onKeydown);
    window.addEventListener('resize', refreshBoxes);
  });

  onUnmounted(() => {
    window.removeEventListener('keydown', onKeydown);
    window.removeEventListener('resize', refreshBoxes);
    window.removeEventListener('mousemove', onFillMove);
    window.removeEventListener('mouseup', onFillEnd);
  });

  return {
    activeCell,
    cellEditor,
    cellEditorInput,
    selectionBox,
    fillBox,
    boxStyle,
    refreshBoxes,
    onGridClick,
    onGridDblClick,
    onEditorKeydown,
    commitEdit,
    startFill,
  };
}
