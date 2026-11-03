# TimonConfigs Crimson — motyw 0.16.0

Zmiany przygotowano na podstawie GitHub `main` `5388965` (Core 1.1.13, motyw 0.15.1). To aktualizacja wyglądu i publicznych obrazów. Core, płatności, role Discorda, endpoint uploadu Ownera i chronione pliki pozostają bez zmian.

## Wygląd i grafiki

- Grafitowe tła, srebrna typografia i czerwone akcenty dopasowane do dostarczonego emblematu Timon.
- Domyślna identyfikacja: okrągły emblemat i tekstowy napis TimonConfigs. Zapisane własne logo WordPressa ma pierwszeństwo.
- Nowa, oryginalna ilustracja inspirowana Apex Legends: postać inspirowana Wraith, arena science-fiction, czerwone wyładowania. Nie jest zrzutem rozgrywki. Emblemat przygotowano z obrazu dostarczonego przez właściciela; tło hero wygenerowano dla tego projektu.
- WebP hero: 1817 × 866, 91 628 B; warianty 960 × 458 (30 088 B) i 640 × 305 (15 366 B). Emblemat: 512 × 512, 59 880 B, przezroczyste narożniki. Są to rozmiary plików, nie pomiar szybkości produkcji ani Core Web Vitals.
- Animacja CSS: powolny ruch obrazu, poświaty i trzech drobnych iskier. Bez wideo, canvasu lub dodatkowej biblioteki. Pauza przyciskiem, po wyjściu hero poza ekran lub ukryciu karty. `prefers-reduced-motion` wyświetla statyczną grafikę. Bez JavaScript grafika również pozostaje statyczna.
- Na telefonie obraz znajduje się pod tekstem. Istniejące adresy kolekcji, wyszukiwarka, karty gier i treści pozostają zachowane.

## Aktualizacja

1. Zachowaj kopię bieżącego motywu i bazy. Wgraj `timonconfigs-theme-0.16.0.zip` w **Wygląd → Motywy → Dodaj nowy → Wyślij motyw** i zastąp dotychczasowy TimonConfigs. Core 1.1.13 pozostaje zainstalowany. Nie uruchamiaj ponownie instalatora ani nie importuj ponownie JSON-a Rank Math.
2. Wyczyść cache witryny/CDN/przeglądarki i sprawdź Home w incognito. Istniejące ustawienie wskazujące dokładnie na stare stockowe obrazy tego motywu jest aktualizowane wyłącznie podczas renderowania. Własne inne adresy obrazów pozostają zachowane.
3. Jeśli masz ustawione stare logo w **Wygląd → Dostosuj → Tożsamość witryny**, usuń ten wybór, aby pokazać nowy domyślny emblemat z napisem. Możesz zamiast tego wybrać własny obraz z Biblioteki mediów. Zapisane własne logo nie jest automatycznie zastępowane.
4. **Wygląd → Dostosuj → TimonConfigs site content** pozwala zmienić hero, jego tekst alternatywny i wyłączyć animację. W edytorze Home blok Hero zachowuje własne nagłówki, CTA i obraz; ma dodatkowe pole alternatywnego tekstu obrazu.
5. Sprawdź Home na telefonie i komputerze, menu, wyszukiwarkę, konto, checkout i panel Ownera. Rzeczywiste płatności i nadawanie ról nie były częścią tej zmiany.

## Metadane obrazów i Rank Math

Plik `assets/images/crimson-assets.json` zawiera nazwę pliku, rzeczywiste wymiary, tytuł, alt, podpis i opis obu obrazów. Hero korzysta z opisowego alt, `srcset`, `sizes`, wymiarów i wysokiego priorytetu pobierania. Emblemat przy tekstowym napisie w nagłówku ma pusty alt, aby czytnik ekranu nie powtarzał nazwy marki.

Aby mieć te same obrazy jako edytowalne pozycje Biblioteki mediów, otwórz **Wygląd → TimonConfigs brand assets → Add bundled artwork to Media Library**. Jest to jawny import dwóch publicznych obrazów, wymagający uprawnień administratora i dodawania mediów. Kolejny import używa tych samych wpisów i zachowuje późniejsze własne tytuły, alt, podpisy i opisy. Nie zmienia wybranego logo ani obrazu hero i nie dotyczy prywatnych plików konfiguracyjnych.

Przy aktywnym Rank Math zachowano jego wyłączną obsługę tytułów, Open Graph i JSON-LD. Jeśli chcesz nowy obraz także przy udostępnianiu linku, wybierz importowane hero w **Rank Math → Titles & Meta → Global Meta → OpenGraph Thumbnail**, a emblemat w ustawieniach logo organizacji **Local SEO**. Indywidualny obraz społecznościowy strony ma nadal pierwszeństwo; sprawdź również zakładkę Social dla Home. Nie nadpisuj własnych tytułów lub opisów dla samej zmiany obrazu.

Bez zewnętrznej wtyczki SEO motyw używa nowego hero jako ostatniego fallbacku obrazu udostępniania i podaje jego wymiary/alt. Własny obraz społecznościowy, grafika kolekcji lub wyróżniony obraz mają dotychczasowe pierwszeństwo. Organizacja otrzymuje domyślny emblemat, jeśli nie ma własnego logo.

Sama zmiana grafiki nie wymaga ponownego ręcznego zgłaszania wszystkich adresów do GSC. Zachowaj poprawną mapę witryny; po wdrożeniu można sprawdzić aktualny HTML Home i podgląd obrazu. Nie zmieniano canonical, adresów kolekcji, noindex lub ustawień map. Grafika i wynik analizatora nie gwarantują pozycji w Google.

## Weryfikacja

Zaliczone: 230 asercji PHP (obrazy/import, Home, SEO, kolekcje), 44 widoki Chromium obejmujące publiczne strony i logowanie, lint126PHP oraz105 asercji świeżej instalacji finalnego ZIP-a w oddzielnym WordPressie offline. Bez błędów JS/PHP, uszkodzonych obrazów lub poziomego przewijania w sprawdzonych widokach. Wyniki i pliki podglądów opisano w `PROGRESS.md`. Podgląd motywu `screenshot.png` pochodzi z rzeczywistego lokalnego WordPressa w oknie 1200 × 900; nie zmienia układu ani nie ukrywa sekcji. Zrzuty komputer/telefon i manifest paczki znajdują się w `artifacts/`. Brak potwierdzonej instalacji na produkcji.
