// WordPress globals, re-exported so the rest of the app can import them like modules.
const { element, apiFetch, i18n } = window.wp;

export const {
  createElement,
  Fragment,
  useState,
  useEffect,
  useLayoutEffect,
  useRef,
  useMemo,
  useCallback,
  useSyncExternalStore,
  createPortal,
  createRoot,
  memo,
} = element;

export const api = apiFetch;
export const __ = i18n.__;
export const sprintf = i18n.sprintf;

export const config = window.brikBuilder;
