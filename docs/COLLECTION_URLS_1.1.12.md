# Kolekcje gra + urządzenie — Core 1.1.12 / motyw 0.15.0

Zmiana tworzy rzeczywiste strony WordPress dla fraz takich jak „XIM Matrix Apex Legends”. Karty wynikają z przecięcia gry i urządzenia, a nie z nazwy karty lub miejsca na ogólnej stronie gry. Istniejące konfiguracje, pliki, płatności, członkostwa i kolekcje gier pozostają zachowane.

## Adresy i wyszukiwanie

| Fraza | Adres |
| --- | --- |
| XIM Matrix Apex Legends | `/xim-matrix-apex-legends/` |
| XIM Matrix Call of Duty | `/xim-matrix-call-of-duty/` |
| Cronus Zen Apex Legends | `/cronus-zen-apex-legends/` |
| Cronus Zen Call of Duty | `/cronus-zen-call-of-duty/` |
| Free Configs | `/free-configs/` |

Schemat obejmuje również Fortnite, Rainbow Six Siege, Rust, EA Sports FC i NBA 2K: `/{xim-matrix|cronus-zen}-{fortnite|rainbow-six-siege|rust|ea-sports-fc|nba-2k}/`. Łącznie powstaje 14 stron urządzenie + gra i jedna Free. Pozostałe skróty zachowują własne istniejące adresy: `/xim-matrix-configs/`, `/cronus-zen-configs/`, `/apex-legends-configs/`, `/vip-monthly/`, `/video-showcase/`.

Wyszukiwanie rozpoznaje pełne frazy i aliasy, np. `xim matrix apex legends`, `xim-matrix-call-of-duty`, `cronus zen COD` i `free configs`. Słowa dotyczące wydania, np. `Predator S30`, pozostają dodatkowym warunkiem tekstowym. Wybrane filtry i kontekst platformy są zachowane. Zmiana gry/urządzenia w formularzu prowadzi do odpowiedniej kolekcji; dodatkowe filtry mogą zawężać wyniki. Paginacja korzysta z `?collection_page=2` i własnego canonical; nieistniejąca strona wyników zwraca 404.

## Bezpieczna aktualizacja

1. Zachowaj kopię bazy i aktualnych katalogów motywu/wtyczki. Zainstaluj **Core 1.1.12** i **motyw 0.15.0** razem. Nie uruchamiaj ponownie całego instalatora witryny ani nie importuj ponownie poprzedniego JSON-a tylko dla tej zmiany.
2. Otwórz kokpit jako administrator. Jednorazowy instalator utworzy brakujące strony. Na nowej instalacji robi to także zwykły jawny setup. Nie nadpisuje istniejących autorskich stron, edycji, statusu draft ani usuniętych stron z oznaczeniem kolekcji. Kolizja sluga z inną stroną pozostaje do świadomego rozstrzygnięcia w panelu; taka strona nie jest automatycznie przejmowana.
3. W **Strony** sprawdź nowe pozycje. W Gutenbergu można edytować ich tytuły i opis; odpowiednie karty i filtry generuje motyw. Indywidualne metadane Rank Math mają pierwszeństwo przed domyślnymi danymi TimonConfigs.
4. Zapisz **Rank Math → Sitemap Settings**, następnie wyczyść cache witryny/Hostinger/LiteSpeed. Otwórz nowe adresy anonimowo i sprawdź karty, pojedynczy H1, tytuł, opis, canonical i robots. Zapis bezpośrednich odnośników jest potrzebny tylko wtedy, gdy instalacja mimo publikacji strony nadal zwraca 404; nie zmieniaj samej struktury permalinków.
5. Sprawdź wyszukiwanie pełnej frazy i nazwy konkretnego wydania, zmianę filtrów, drugą stronę wyników i chronione pobrania po zalogowaniu.

Znane lokalne odnośniki `/{game}-configs/#xim-matrix` i `#cronus-zen` w Hero, Gutenbergu i menu są aktualizowane podczas renderowania. Nie zmienia to zapisanej treści ani niezwiązanych linków zewnętrznych. Stare kotwice nadal istnieją na ogólnych kolekcjach. Fragment po `#` nie trafia do serwera, dlatego nie można zastosować osobnego serwerowego 301 dla każdej starej kotwicy. Nie przekierowuj całej kolekcji Apex na XIM: nadal służy również użytkownikom Cronus.

## Indeksowanie i limit GSC

Nowa strona może być opublikowana, ale pozostawać `noindex`, jeśli nie ma dostępnej konfiguracji przypisanej jednocześnie do danej gry i urządzenia. Uwzględniane są również autorskie wykluczenia stron/terminów, hasło strony i użyteczny opis obu kolekcji. Warianty z dodatkowymi filtrami są noindex; konta, checkout i prywatne pobrania pozostają wykluczone.

Rank Math i natywna mapa WordPressa automatycznie uwzględniają kwalifikujące się nowe strony; nie potrzeba osobnych zgłoszeń XML dla każdej frazy. Sprawdź aktualny `https://timonconfigs.com/sitemap_index.xml` i jego mapę stron po wyczyszczeniu cache.

Wyczerpanie limitu **Poproś o zindeksowanie** nie zatrzymuje zwykłego pobierania strony ani odczytu mapy przez Google. Poczekaj, aż GSC ponownie udostępni tę funkcję; nie trzeba zgłaszać wszystkich konfiguracji ręcznie. Później priorytetem są cztery główne adresy z tabeli, jeśli ich treść i wydania są gotowe. Zgłoszenie lub obecność w mapie nie gwarantuje indeksowania, terminu ani pozycji.

Ponieważ osobna strona przejmuje frazę „XIM Matrix Apex Legends”, warto opisywać Home szerzej jako katalog Cronus Zen i XIM Matrix, a dokładną intencję rozwijać na nowej kolekcji. Nie zmieniano automatycznie autorskiego tytułu Home ani poprzedniego eksportu Rank Math. Nie ma potrzeby ponownego zgłaszania starych ogólnych kolekcji tylko z powodu dodania nowych stron.

## Walidacja i stan publikacji

Wyniki końcowych testów, commit i paczki zapisano na początku `PROGRESS.md` oraz `NEXT_SESSION.md`. Zmiany są przygotowane do ręcznej aktualizacji; push gałęzi GitHub nie potwierdza instalacji WordPressa na Hostingerze. Nie wykonano zmian produkcyjnej bazy, płatności lub ról Discord.
