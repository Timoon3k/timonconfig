# Uzupełnienie kontaktu — zintegrowane wydanie Core 0.9.8

Właściciel podał publiczne dane do regulaminów:

- **E-mail:** contact@timonconfigs.com.
- **Adres:** TimonConfigs, ul. Dąbrowica 21, 05-326 Poświetne, Poland. Zachowano podaną pisownię miejscowości.
- **Sprzedawca:** Tomasz Majewski; dotychczasowa forma działalności nierejestrowanej w Polsce pozostaje bez zmian. Samo podanie adresu nie zmienia jej w zarejestrowaną spółkę.

Kontaktowy checkpoint miał lokalnie numer 0.9.7. Zdalny main równolegle użył 0.9.7 dla poprawki Stripe, dlatego wspólne, aktualne wydanie ma numer **0.9.8** i obejmuje obie zmiany. Core 0.9.8 uzupełnia publiczne dane i jednorazowo publikuje ponownie Terms of Service, Privacy Policy oraz Refund & Cancellation Policy. Nowy znacznik `2026-10-26-legal-v2` pozwala zaktualizować również instalację 0.9.6, która zapisała puste dane i zakończoną publikację `v1`. Aktualizacja podmienia wyłącznie dwa pola kontaktowe profilu na dane wydania; inne zapisane informacje sprzedawcy pozostają zachowane. Ponowne wejścia do kokpitu po sukcesie nie nadpisują kolejnych edycji administratora.

Przed podmianą stron działa istniejące prywatne archiwum. Kanoniczne ID, status `publish`, Gutenberg i przekierowania są zachowane. Nie zmieniano billing/OAuth, sekretów ani zamówień klientów. Podanie danych uzupełnia wcześniejszy brak w treści; nie jest niezależną weryfikacją adresu, działania skrzynki ani ogólnej zgodności prawnej.

## Aktualizacja WordPressa

Wgraj `artifacts/timonconfigs-core-0.9.8.zip`, zastępując obecną wtyczkę po zwykłej kopii bazy. Aktualny motyw 0.11.3 dodatkowo zmienia wyróżniony pakiet i benefity; sam mechanizm podmiany regulaminów pozostaje we wtyczce. Otwórz kokpit jako administrator i sprawdź wszystkie trzy regulaminy oraz cache. Właściciel może później zmienić kontakt w **Ustawienia → Legal documents** i ponownie opublikować dokumenty. Sam push GitHub nie potwierdza aktualizacji WordPressa na Hostingerze.

Testy regresji publikatora obejmują aktualizację `v1` z pustymi danymi, zachowanie pozostałego profilu i ID stron, brak nowego znacznika po błędzie oraz idempotencję `v2`. Test przeglądarkowy sprawdza rzeczywiste dane i linki mailto na wszystkich trzech stronach w czterech szerokościach; istniejący screenshot motywu jest zachowany. Wyniki są na początku `PROGRESS.md`; manifest paczki i logi: `artifacts/legal-097-*`.
