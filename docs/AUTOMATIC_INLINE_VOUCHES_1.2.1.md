# Automatyczne opinie na stronie — Core 1.2.1 / motyw 0.17.1

Nowe zwykłe wiadomości tekstowe członków kanału vouches są automatycznie publikowane jako pełne karty opinii na stronie głównej i `/reviews/`. Czytelnik widzi treść, autora i datę bez przechodzenia do Discorda. Mały link „View original on Discord” pozostaje źródłem cytatu. Wiadomości botów i embedy nadal są pomijane, zgodnie z wybranym wcześniej źródłem tekstowym.

## Co zmieniono

- Sprawdzanie nowych wiadomości co minutę zamiast co 15 minut. Pierwsze zadanie jest należne od razu po uruchomieniu WordPressa z dostępnym prywatnym tokenem i włączoną synchronizacją.
- Istniejące zadanie ze starego wydania automatycznie migruje do nowego harmonogramu. Kolejne wejścia na stronę nie przesuwają najbliższego sprawdzenia. HTTP do Discorda pozostaje zadaniem w tle, poza renderowaniem strony.
- Nowe opinie są publikowane bez ręcznego zatwierdzania. Po zmianie danych importer wysyła purge cache Home i Reviews do LiteSpeed. Zachowano ochronę przed duplikatami, obsługę edycji/usunięć, limity Discorda i decyzje administratora o ukryciu opinii.
- Stan pusty nie odsyła już gościa do Discorda zamiast opinii. Pokazuje prawdziwą informację o braku opublikowanych wpisów. Administrator otrzymuje dodatkowo link do ustawień importera.
- **Reviews → Discord vouches** pokazuje, czy automatyczne publikowanie jest włączone, zatrzymane albo czeka na token; podaje najbliższe sprawdzenie, ostatni udany import i błędy połączenia.

## Aktualizacja i uruchomienie

1. Wgraj motyw `timonconfigs-theme-0.17.1.zip`, następnie `timonconfigs-core-1.2.1.zip`. Wyczyść cache LiteSpeed/hostingu/CDN. Nie uruchamiaj ponownie instalatora ani importu Rank Math.
2. Otwórz kokpit. W **Reviews → Discord vouches** pozostaw zaznaczone **Enable automatic synchronization**. Domyślny kanał to `1560781856931315832` na serwerze `1557854161805115402`.
3. Status **Bot credentials** musi wskazywać dostępny token prywatny. Wykorzystywana jest dotychczasowa stała bota Woo; opcjonalnie `TIMONCONFIGS_VOUCHES_DISCORD_BOT_TOKEN`. Sam URL kanału oraz uprawnienie Administrator bota nie udostępniają tokena WordPressowi. Nie publikuj tokena w czacie ani repozytorium.
4. W aplikacji bota włącz **Message Content Intent**. Bot potrzebuje odczytu kanału i historii. Nie wysyła wiadomości na Discordzie. Przycisk **Synchronize now** służy do natychmiastowego sprawdzenia, ale nie jest wymagany przy każdej nowej opinii.
5. Dla regularnego działania również bez odwiedzających ustaw harmonogram hostingu co minutę, uruchamiający lokalny `wp-cron.php` właściwej instalacji WordPressa lub `wp cron event run --due-now` ze wskazaniem tej instalacji. Dobierz faktyczną ścieżkę i interpreter PHP na hostingu. Sam WP-Cron zależy od uruchomień WordPressa; pełny cache, mały ruch i limity Discorda mogą opóźnić publikację.

Po udanej synchronizacji nowe cytaty są obecne w HTML Home i Reviews, również dla gościa i bez JavaScriptu. Otwarta wcześniej karta pokazuje nowe wpisy po ponownym wczytaniu. Nie jest to połączenie czasu rzeczywistego z Discord Gateway. Karty prezentują do 50 najnowszych/poukładanych wpisów według istniejącego porządku redakcyjnego.

## Walidacja

Testy obejmują migrację harmonogramu, brak duplikatów zadań, automatyczne opublikowanie nowej wiadomości przez hook cron, pełną treść w szablonie dla gościa oraz purge obu stron. Dotychczasowe zabezpieczenia importera i testy wyglądu pozostają w zestawie regresji. Zaliczone: 227 asercji PHP importera, 354 asercje przeglądarkowe w 22 widokach, lint 124 plików PHP i 113 asercji świeżej instalacji finalnych ZIP-ów. Fixture i tymczasowe zasoby usunięto. Wyniki końcowe są zapisane w `PROGRESS.md` i `artifacts/`.

Nie wykonywano w tej sesji uwierzytelnionego odczytu prywatnego Discorda ani instalacji na hostingu. Lokalna walidacja korzysta wyłącznie z oznaczonych danych QA i symulowanych odpowiedzi API. Push na GitHub nie potwierdza wdrożenia produkcyjnego.
