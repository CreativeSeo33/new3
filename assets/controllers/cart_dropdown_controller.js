import { Controller } from "@hotwired/stimulus"

export default class extends Controller {
  static targets = ["dropdown", "trigger"]

  hideTimer = null
  isOpen = false

  connect() {
    // события из data-action
  }

  enter() {
    clearTimeout(this.hideTimer)
    this.show()
  }

  leave(e) {
    clearTimeout(this.hideTimer)
    if (e && e.relatedTarget && this.element.contains(e.relatedTarget)) return
    this.hideTimer = setTimeout(() => this.hide(), 150)
  }

  focusIn() {
    clearTimeout(this.hideTimer)
    this.show()
  }

  focusOut(e) {
    if (e && e.relatedTarget && this.element.contains(e.relatedTarget)) return
    this.hide()
  }

  onKeydown(e) {
    if (e.key === "Escape" && this.isOpen) {
      this.hide()
      try { this.triggerTarget?.focus() } catch {}
    }
  }

  onDocumentClick(e) {
    if (!this.element.contains(e.target)) {
      this.hide()
    }
  }

  show() {
    if (this.isOpen) return
    this.isOpen = true
    this.dropdownTarget.classList.remove("opacity-0", "invisible", "pointer-events-none")
    this.dropdownTarget.classList.add("opacity-100", "visible", "pointer-events-auto")
    this.toggleAria(true)
  }

  hide() {
    if (!this.isOpen) return
    clearTimeout(this.hideTimer)
    this.isOpen = false
    this.dropdownTarget.classList.add("opacity-0", "invisible", "pointer-events-none")
    this.dropdownTarget.classList.remove("opacity-100", "visible", "pointer-events-auto")
    this.toggleAria(false)
  }

  toggleAria(open) {
    if (this.hasTriggerTarget) {
      this.triggerTarget.setAttribute("aria-expanded", String(open))
    }
    this.dropdownTarget.setAttribute("aria-hidden", String(!open))
  }
}


