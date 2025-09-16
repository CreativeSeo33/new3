export interface CityModalTriggerOptions {}

export function init(root: HTMLElement): () => void {
  const onClick = (e: Event) => {
    e.preventDefault();
    window.dispatchEvent(new CustomEvent('city-modal:open'));
  };
  root.addEventListener('click', onClick);
  return () => root.removeEventListener('click', onClick);
}


