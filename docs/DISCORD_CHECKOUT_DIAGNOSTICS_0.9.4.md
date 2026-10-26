# Discord przed checkoutem — Core 0.9.4

Komunikat „Discord returned an unavailable or invalid response” ze starej wersji łączył różne błędy kontroli bota. Ze zrzutu nie można ustalić konkretnego statusu. Publiczne zakupy i logowanie Discord nie zastępują poprawnej konfiguracji bota do nadawania VIP.

Po aktualizacji Core do 0.9.4 ponów próbę. Komunikat błędu żądania zawiera bezpieczne `Reference` z etapem i HTTP:

| Wynik | Działanie administratora |
| --- | --- |
| HTTP401 | W Discord Developer Portal → Bot użyj aktualnego Token i zapisz go w Ustawienia → TimonConfigs billing → Live → Discord Bot token. |
| HTTP403 | Sprawdź, czy bot jest dodany do właściwego serwera i ma do niego dostęp. |
| HTTP404 | Sprawdź Guild ID oraz członkostwo bota i kupującego we właściwym serwerze. |
| HTTP429 / 5xx / network | Odczekaj i ponów; może to być ograniczenie lub awaria Discorda. |
| unexpected response | Odpowiedź jest niepoprawna; przekaż bezpieczny kod i Reference do diagnostyki. |

Jeśli bot nie jest dodany: Discord Developer Portal → OAuth2 → URL Generator → zaznacz scope `bot` i uprawnienie `Manage Roles`, otwórz wygenerowany adres i wybierz serwer. Ustaw rolę bota ponad VIP Monthly i Lifetime. Poprawki nie nadają mu Ownera i nie zmieniają ról zdalnie.

Samo OAuth użytkownika może działać bez bota na serwerze. Natomiast nowy checkout Live odczytuje przez bota serwer i role przed utworzeniem nowego zamówienia oraz sesji płatności. Błąd preflight zatrzymuje tę próbę przed wywołaniem operatora; nie jest to potwierdzenie historii wcześniejszych obciążeń.

Nie wysyłaj tokenów, sekretów ani pełnych callback URL. Do ustalenia kolejnego błędu wystarcza HTTP i stały etap, np. `guild_access / HTTP 403`. Nie wpisano sekretów ani nie zweryfikowano faktycznego bota na Hostingerze.

Weryfikacja lokalna: 263 asercje PHP, 97 kontroli instalacji ZIP; HTTP Discord/Stripe/PayPal jest symulowane. Kod `5c4e510` opublikowano na GitHub main; instalacja na hostingu pozostaje odrębną czynnością.
