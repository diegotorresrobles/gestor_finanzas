import { useEffect, useId, useRef } from "react";
import { createPortal } from "react-dom";
import Button from "./Button";

function Modal({ isOpen, type, title, actions = [], onClose, onSubmit, onChange, onReset, method = 'POST', disableClose = false, children }) {
  const dialogRef = useRef(null);
  const titleId = useId();

  useEffect(() => {
    if (!isOpen) return;
    const dialog = dialogRef.current;
    const previousFocus = document.activeElement;
    const previousOverflow = document.body.style.overflow;
    dialog.showModal();
    document.body.style.overflow = 'hidden';
    dialog.querySelector('input:not([disabled]), select:not([disabled]), textarea:not([disabled])')?.focus();
    const focusableElements = () => Array.from(dialog.querySelectorAll('button:not([disabled]), input:not([disabled]), select:not([disabled]), textarea:not([disabled]), a[href], [tabindex]:not([tabindex="-1"])')).filter((element) => element.getClientRects().length > 0 && element.tabIndex >= 0);
    const keepFocus = (event) => {
      if (event.key !== 'Tab') return;
      const elements = focusableElements();
      const first = elements[0];
      const last = elements[elements.length - 1];
      if (!first) { event.preventDefault(); dialog.focus(); return; }
      if (!dialog.contains(document.activeElement)) { event.preventDefault(); first.focus(); }
      else if (event.shiftKey && document.activeElement === first) { event.preventDefault(); last.focus(); }
      else if (!event.shiftKey && document.activeElement === last) { event.preventDefault(); first.focus(); }
    };
    const restoreModalFocus = (event) => {
      if (dialog.open && !dialog.contains(event.target)) (focusableElements()[0] ?? dialog).focus();
    };
    document.addEventListener('keydown', keepFocus, true);
    document.addEventListener('focusin', restoreModalFocus);
    return () => {
      document.removeEventListener('keydown', keepFocus, true);
      document.removeEventListener('focusin', restoreModalFocus);
      dialog.close();
      document.body.style.overflow = previousOverflow;
      if (previousFocus?.isConnected) previousFocus.focus();
    };
  }, [isOpen]);

  // Disabling the focused submit button can move focus to the document body.
  useEffect(() => {
    const dialog = dialogRef.current;
    if (isOpen && dialog?.open && !dialog.contains(document.activeElement)) {
      (dialog.querySelector('[aria-invalid="true"]:not([disabled]), input:not([disabled]), select:not([disabled]), textarea:not([disabled])') ?? dialog).focus();
    }
  });

  const close = () => { if (!disableClose) onClose?.(false); };
  if (!isOpen) return null;
  const Container = onSubmit ? 'form' : 'div';

  return createPortal(
    <dialog ref={dialogRef} tabIndex={-1} aria-labelledby={titleId} aria-modal="true"
      onCancel={(event) => { event.preventDefault(); close(); }}
      onClick={(event) => {
        const bounds = event.currentTarget.getBoundingClientRect();
        if (event.target === event.currentTarget && (event.clientX < bounds.left || event.clientX > bounds.right || event.clientY < bounds.top || event.clientY > bounds.bottom)) close();
      }}
      className="fixed inset-0 m-auto max-h-[calc(100dvh-2rem)] w-[calc(100%-2rem)] max-w-lg overflow-y-auto rounded-xl border border-gray-300 bg-white p-0 text-gray-800 shadow-xl backdrop:bg-black/40 backdrop:backdrop-blur-xs dark:border-gray-600 dark:bg-gray-800 dark:text-gray-200"
    >
      <Container onSubmit={onSubmit} onChange={onChange} onReset={onReset} method={onSubmit ? method : undefined}>
        <header className="flex items-start justify-between gap-4 border-b border-gray-200 p-5 dark:border-gray-600">
          <h2 id={titleId} className="text-xl font-semibold">{title}</h2>
          <button type="button" onClick={close} disabled={disableClose} aria-label="Cerrar modal" className="cursor-pointer rounded-md px-2 text-2xl leading-none text-gray-500 hover:bg-gray-100 focus-visible:outline-2 focus-visible:outline-blue-700 disabled:opacity-50 dark:text-gray-400 dark:hover:bg-gray-700 dark:focus-visible:outline-blue-400">×</button>
        </header>
        <div className="p-5">
          {type === 'success' && <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" strokeWidth="1.5" strokeLinecap="round" strokeLinejoin="round" aria-hidden="true" className="mx-auto mb-5 size-16 text-green-500"><circle cx="12" cy="12" r="10" /><path d="m16 9-5.5 5.5L8 12" /></svg>}
          {children}
        </div>
        {actions.length > 0 && <footer className="flex flex-wrap justify-end gap-3 border-t border-gray-200 p-5 dark:border-gray-600">
          {actions.map((action, index) => <Button key={action.key ?? index} type={action.type ?? 'button'} variant={action.variant ?? 'secondary'} label={action.label} value={action.value} disabled={action.disabled} action={action.type === 'submit' || action.type === 'reset' ? undefined : (action.action ?? onClose)} />)}
        </footer>}
      </Container>
    </dialog>, document.body,
  );
}

export default Modal;
