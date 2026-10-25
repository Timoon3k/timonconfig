# Płatności na stronie — linki do Ticket Tool

2026-10-25 · Core 0.9.1 / motyw 0.11.1

Ticket Tool na Discordzie ma odsyłać do płatności na stronie. Nowe zakupy nie korzystają z Upgrade.chat. Kod nie konfiguruje zdalnie bota ani istniejących ticketów; poniższą wiadomość lub przyciski dodaj w używanym panelu Ticket Tool.

| Przycisk | Docelowy adres |
| --- | --- |
| Browse Memberships | https://timonconfigs.com/pricing/ |
| VIP Monthly | https://timonconfigs.com/checkout/monthly/ |
| VIP Lifetime | https://timonconfigs.com/checkout/lifetime/ |

Te same linki, generowane dla bieżącej domeny WordPressa, są do skopiowania w **Ustawienia → TimonConfigs**. Nie wklejaj do Ticket Tool kluczy API, sekretów, callbacków OAuth, cudzych identyfikatorów ani jednorazowych tokenów. Ustawienia płatności znajdują się osobno w **Ustawienia → TimonConfigs billing** (`tc-billing`).

Gotowa publiczna wiadomość po angielsku:

```text
TimonConfigs memberships

Browse plans: https://timonconfigs.com/pricing/
VIP Monthly — €29.99/month: https://timonconfigs.com/checkout/monthly/
VIP Lifetime — €80 one time: https://timonconfigs.com/checkout/lifetime/

Continue with Discord on the website using the same Discord account that should receive access. Review the plan and renewal terms, then complete payment through an available secure checkout.

VIP access is activated only after the server verifies payment and synchronizes your Discord access. Opening a ticket, sending a screenshot or returning from checkout does not confirm payment.

If checkout is not available yet, keep this ticket open and ask support for help. Never send card details, passwords or account tokens in a ticket.
```

Ceny są zawsze centralne: Free €0, Monthly €29.99/miesiąc, Lifetime €80 jednorazowo. Same linki i ceny nie uruchamiają sprzedaży: domyślny `routing=disabled` pokazuje informację o niedostępności i nie przyjmuje płatności. Najpierw skonfiguruj i przetestuj Stripe Test na odizolowanym stagingu, następnie PayPal Sandbox według [instrukcji](DIRECT_BILLING_SETUP.md). Live wymaga odrębnej zgody i konfiguracji; wiadomość w Ticket Tool nie jest taką zgodą.

Istniejące konta, potwierdzone wcześniejsze zakupy i ważne uprawnienia pozostają zachowane. Nie nadajemy VIP na podstawie samego ticketa lub zdjęcia płatności. Zakończona konfiguracja Ticket Tool i rzeczywista transakcja testowa muszą zostać sprawdzone na właściwym serwerze — nie zostały wykonane przez tę zmianę kodu.
