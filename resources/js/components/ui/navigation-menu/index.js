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
  "group inline-flex h-9 w-max items-center justify-center rounded-lg bg-transparent px-3.5 py-2 text-sm font-medium text-gray-700 font-sans transition-colors hover:bg-gray-100 hover:text-gray-900 focus:bg-gray-100 focus:text-gray-900 focus:outline-none disabled:pointer-events-none disabled:opacity-50 data-[active]:bg-gray-100 data-[active]:text-gray-900 data-[state=open]:bg-gray-100 data-[state=open]:text-gray-900 cursor-pointer select-none",
);
