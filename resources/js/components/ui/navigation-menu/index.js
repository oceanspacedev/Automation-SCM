import { cva } from "class-variance-authority";

export { default as NavigationMenu } from "./NavigationMenu.vue";
export { default as NavigationMenuContent } from "./NavigationMenuContent.vue";
export { default as NavigationMenuIndicator } from "./NavigationMenuIndicator.vue";
export { default as NavigationMenuItem } from "./NavigationMenuItem.vue";
export { default as NavigationMenuLink } from "./NavigationMenuLink.vue";
export { default as NavigationMenuList } from "./NavigationMenuList.vue";
export { default as NavigationMenuTrigger } from "./NavigationMenuTrigger.vue";
export { default as NavigationMenuViewport } from "./NavigationMenuViewport.vue";

export const navigationMenuTriggerStyle = cva(
  "group inline-flex h-9 w-max items-center justify-center rounded-lg bg-transparent px-3.5 py-2 text-sm font-medium text-gray-700 dark:text-slate-300 font-sans transition-colors hover:bg-gray-100 dark:hover:bg-slate-800 hover:text-gray-900 dark:hover:text-white focus:bg-gray-100 dark:focus:bg-slate-800 focus:text-gray-900 dark:focus:text-white focus:outline-none disabled:pointer-events-none disabled:opacity-50 data-[active]:bg-gray-100 dark:data-[active]:bg-slate-800 data-[active]:text-gray-900 dark:data-[active]:text-white data-[state=open]:bg-gray-100 dark:data-[state=open]:bg-slate-800 data-[state=open]:text-gray-900 dark:data-[state=open]:text-white cursor-pointer select-none",
);
