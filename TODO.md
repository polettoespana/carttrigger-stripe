# TODO

## Da testare in produzione

### Confermati OK
- [x] Carte (Payment Element)
- [x] Apple Pay dal checkout (utente loggato)
- [x] Bizum
- [x] Webhook — eventi arrivano correttamente, da monitorare nel tempo.

### Da verificare

- [ ] **Apple Pay dal checkout come utente ospite** — verificare che i campi billing vuoti vengano popolati correttamente e che la normalizzazione stato/provincia funzioni (es. "Madrid" → "M").
- [ ] **Apple Pay dal carrello** (shortcode ECE) — confermare che funzioni end-to-end in produzione con importi reali.
- [ ] **Google Pay** — stesso flusso di Apple Pay, mai testato esplicitamente.
- [ ] **Klarna o altri metodi asincroni** — il pagamento si completa via webhook (`payment_intent.succeeded`); verificare che l'ordine passi a "In elaborazione" correttamente senza che l'utente attenda sul browser.
- [ ] **Coupon + ECE carrello** — applicare un coupon, poi pagare con Apple Pay dal carrello; verificare che lo sconto sia applicato nell'ordine creato.
- [ ] **Aggiornamento importo ECE dopo coupon** — i pulsanti Express Checkout mostrano l'importo corretto dopo aver applicato/rimosso un coupon?

### Note future

- **Rimborsi** — attualmente gestiti direttamente dalla dashboard Stripe. L'ordine WC rimane in "In elaborazione" perché WC non riceve notifica. Valutare in futuro la gestione dell'evento `charge.refunded` nel webhook per aggiornare automaticamente lo stato ordine.
