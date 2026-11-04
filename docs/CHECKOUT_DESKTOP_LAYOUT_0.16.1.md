# Checkout na komputerach — motyw 0.16.1

Poprawka bazuje na GitHub main `6bb6cfa`. Core pozostaje w wersji 1.1.13; czerwony motyw, grafiki i dotychczasowe ustawienia są zachowane.

## Problem i poprawka

Formularz WooCommerce był jednocześnie siatką dwóch kolumn. Dodatkowy element dodany przez hook przed danymi klienta mógł zająć pierwszą komórkę siatki: dane klienta trafiały wtedy na prawo, a podsumowanie spadało do następnego wiersza. Odtworzono taki układ w lokalnym WordPressie z rzeczywistym WooCommerce 10.3.5, używając elementu o ID `wc-stripe-express-checkout-element`. Nie odczytano produkcyjnego DOM ani rzeczywistych iframe operatorów.

Formularz ma teraz osobną wewnętrzną siatkę wyłącznie dla dwóch paneli. Hook przed danymi klienta pozostaje nad siatką, a hook po danych klienta w lewej kolumnie. Kolejność wywołań WooCommerce, jeden formularz, jego action, nonce oraz kontenery zamówienia i płatności pozostają zachowane. Na komputerze dane klienta są po lewej, płatność po prawej i obydwa panele zaczynają się na tej samej wysokości. Do 820 px układ przechodzi na jedną kolumnę. Zachowano wyśrodkowany kontener o maksymalnej szerokości 1190 px.

Nie zmieniano walidacji danych rozliczeniowych, konfiguracji operatorów, kwot, podatków, produktów, subskrypcji, webhooków ani uprawnień Discorda.

## Aktualizacja

1. Zachowaj kopię obecnego motywu. W WordPressie wybierz **Wygląd → Motywy → Dodaj nowy → Wyślij motyw**, wgraj `timonconfigs-theme-0.16.1.zip` i zastąp TimonConfigs. Core 1.1.13 pozostaje zainstalowany.
2. Wyczyść cache LiteSpeed/hostingu/CDN i odśwież przeglądarkę. Wersja zasobów CSS została podniesiona do 0.16.1.
3. Sprawdź checkout w zwykłym powiększeniu przeglądarki 100%: dane po lewej i płatność po prawej na komputerze, jedna kolumna na telefonie. Zweryfikuj zmianę kraju, przeliczenie podsumowania oraz wybór istniejących metod płatności.
4. Nie importuj ponownie JSON-a Rank Math ani nie uruchamiaj instalatora witryny dla tej poprawki.

Push do GitHub nie potwierdza instalacji na hostingu. W tej sesji nie wykonano rzeczywistej transakcji Stripe/PayPal. Podglądy testowe jawnie oznaczają lokalne zastępcze pola bramek; testy korzystają z prawdziwego szablonu i skryptów checkoutu WooCommerce.

## Weryfikacja

Wyniki końcowych testów, stan gałęzi i manifest paczki znajdują się na początku `PROGRESS.md`. Test przeglądarkowy odtwarza błąd przed poprawką i sprawdza układ bez dodatkowych elementów oraz z elementami hooków przed/po danych klienta. Lokalny fixture jest ograniczony do localhost, losowego nagłówka i sesji w pamięci; nie może przyjąć zamówienia lub wykonać płatności i jest usuwany po teście.
