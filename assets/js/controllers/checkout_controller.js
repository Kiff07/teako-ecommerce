import { Controller } from '@hotwired/stimulus';

export default class extends Controller {
    static targets = ['addressField', 'pickupNote'];

    connect() {
        this.sync();
        this.element.querySelectorAll('input[name$="[deliveryMode]"]').forEach((r) => r.addEventListener('change', () => this.sync()));
    }

    sync() {
        const mode = this.element.querySelector('input[name$="[deliveryMode]"]:checked')?.value;
        const delivery = mode === 'delivery';
        if (this.hasAddressFieldTarget) {
            this.addressFieldTarget.classList.toggle('hidden', !delivery);
            this.addressFieldTarget.querySelectorAll('input,textarea').forEach((el) => { el.required = delivery; });
        }
        if (this.hasPickupNoteTarget) this.pickupNoteTarget.classList.toggle('hidden', delivery);
    }
}
