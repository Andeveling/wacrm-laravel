import { useState } from 'react';

interface DialogState<T> {
  open: boolean;
  target: T | null;
  key: number;
}

/**
 * Modal state where every open is a fresh mount.
 *
 * `key` changes on each `show()`, so the dialog body can read its props
 * once in `useForm` instead of copying them back into state from an
 * effect. Closing only flips `open`, so the exit animation still runs.
 *
 * `T` is what the dialog is about. Pass an id when the body must follow
 * the server copy while it is open, and the record itself when the
 * dialog should survive the list reloading underneath it.
 */
export function useDialog<T = never>() {
  const [state, setState] = useState<DialogState<T>>({
    open: false,
    target: null,
    key: 0,
  });

  return {
    open: state.open,
    target: state.target,
    key: state.key,
    show(target: T | null = null) {
      setState((previous) => ({ open: true, target, key: previous.key + 1 }));
    },
    setOpen(open: boolean) {
      setState((previous) => ({ ...previous, open }));
    },
  };
}
