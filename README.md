webvote
=======

Mobile web application (HTML5/JS) for visitor voting at home-brew competitions (including back-end SQL/PHP-based vote &amp; rule handling). 
Originally designed for competitions arranged by the Swedish home-brew association (SHBF), but supports arbitrary categories &amp; rule-sets.

PREREQUSITES
============
1. An SQL-server supported by PHP PDO 
   (developed and tested with mySQL, 5.5.38-MariaDB-1~wheezy)
2. A Webserver with PHP
   (developed and tested for use @one.com, PHP 5.3)
3. The application (web page texts) is currently ONLY available in SWEDISH. The below documentation is provided
   in English to give eveyone an idea of what this application does.   

HOW TO SETUP
============
1.  Copy all files and subdirectories located in .\vote to your webserver eg youdomain.com/vote, from now on
    referred to as .\vote below. Make sure your webserver defualts to index.php
2.  Create the sql-tables listed in .SQL\import_.sql
    (this is a MySQL import file you can import using phpMyAdmin or similar tool, you'll have to edit the line
    use `NameOfYourDatabase`; before importing)
3.  Use phpmyadmin or similar tool to create at least one administrator user in the table 'vote_users'
    That user should have priviledge=2 (administrator)
    You can also create one or more accounts with privilege=1 for officials that help with vote registration
    during a competition. 
    Note: password is stored as plain text.
4.  Edit .\vote\php\sqlopen_pdo.sql, with database name and user info that matches your SQL-server
5.  Replace .\vote\img\banner.png with your own logo (and possibly adjust CSS)
6.  Edit & follow the instructions in .\vote\php\_config.php to set up a new competition
    Note1:  You'll have to manually create (using phpmyadmin) two tables for each competition category you
            setup in _config.php named vote_cat_xxx & vote_cat_xxx_hitory (xxx being your category name), 
            you can easily use the tables 'vote_cat_example' & 'vote_cat_example_history' as a templates to copy.
    Note2:  The example tables supports UP TO sven votes per category (less is okey), if you need more you'll 
            have to exend your tables with more 'vote_x' / 'vote_x_dt' columns (you dont need to remove columns if
            less than seven).
7.  browse to .\vote\adm.php, and generate an appropriate amount of vote codes for your competition.
    For security you should make sure to delete all existing codes and generate new ones for each new competition
    you set up.
8.  Export vote codes from SQL using phpmyadmin or similar tool to Excel. Find an appropriate way do distribute
    codes to votes among visitors/users (eg on scaps / printed on programme / or similar, printing offices can usually handle
    an excel export)

OVERVIEW OF PAGES
=================
If you have installed according to the instuctions above you will have the following php pages in the root folder of
you web server.

index.php
=========
The main page for voters at your competition. This page is designed for mobile devices
(based on JQuery Mobile, wich supports a very broad range of new and old mobile devices)

manreg.php
==========
Similar to  index.php but this is a simplified (and uglier) version intended for regular computer screens.
It is designed to be used BY VISITORS on PUBLIC computers / voting stations, if you decide to provide such.
(unlike index.php it does not cache previosly used vote codes, once the user presses save the form is reset and
ready for the next visitor)
As it has less logic, you may also inform users that encounter problems with index.php on their mobile devices
to try this page instead (on their mobile device)

mangreg_adm.php (password protected, priviledge=1)
==============================================
This page is intended to be used BY COMPETITION OFFICIALS/MANAGEMENT on regular computers.
You can use this page if you provide visitors with the option to hand in their votes on a paper score cards.
This page is designed for fast typing (Keyboard support tab->tab->Tab->Enter) and has logic to help officials
disqualify incorrect votes without "thinking". The official should just type in whatever has been written on the score
card, if any competition rule is violated the system will inform the official and is given the option to CONFIRM
the violation (system will disqualify automatically), or CORRECT (in case the offial typed wrong).
_config.php has a setting for how to handle votes already registered electronically.
in .\doc folder you'll find documentation of this page to hand out to officials.


vs.php (password protected, priviledge=2)
=========================================
For competition management, shows live results for the competitions.
NOTE: Results are computed live and has to be SAVED/RECORDED from this page, before you clean the database / set up a new competition.

vstat.php (password protected, priviledge=1)
============================================
Live statistics, as table. Shows number of submitted votes and voters by category.
Password protected, but may be shown to the audience on a big screen or similar.

vstat2.php (password protected, priviledge=1)
============================================
Same as vstat.php but as a diagram, Shows number of submitted votes and voters by category.
Password protected, but may be shown to the audience on a big screen or similar.

================================================
*This is where the documentation in English ends*
================================================


 DEVELOPER OVERVIEW:
================================================
vs.php    -> admin_ajax.php
vstat*    -> admin_ajax.php
manreg*   -> manreg_ajax.php
index.php -> vote_ajax.php
en röst kod = en "user".
* Varje tävlingkategori har 2st sql tabeller vote_cat_xxx och vote_cat_xxx_history där röster sparas (en rad per röst kod)
När en röst kod uppdaterar röster sparas till äldre röster till _history (logg)
* röst koder kontrolleras mot vote_codes innan röstning acepteras.
* Inga tävligsrestultat sparas i SQL, utan vs.php (admin_ajax.php) räknar fram resultatet live vid förfrågan.
* Alla tävlingsregler kontrolleras server-side i php (säkerhet), men en hel del även i JS-klient för att minimera felaktiga anrop till php.


DEVELOPER NOTES:
================================================
Systemet är byggt för att hantera många samtidiga användare och samtidigt kunna köras på ett vanligt webbhotell.
Systemet är testat på one.com, som har obegränsad (och snabb) trafik men en begränsing på 20 samtidigt aktiva 
SQL-connections.  De flesta anrop är max några hundra millisekunder långa, och begränsingen skulle träda in 
om mer än 20 användare orsakar det exakt samtidigt, det är kanske inte så troligt ens på SM, men koden är 
hursomhelt designad för att minimera risken.
Därav att tänka på (för anrop från index.php):
  * Open och close av db-connection görs med så lite kod emellan som möjligt, 
    dvs så fort vi fått svar stänger vi (och open/close tar tid, så bara 1ggr per funktion om möjligt)
  * session_variabler används föra att minimera antal anrop mot sql
  * vi undviker komplexa sql frågor
  * Det finns ett antal prestanda inställningar, om behov uppstår ex stänga av loggningstabeller (_config.php), 
    behövdes inte SM 2014
  * SQL tabelldesignen kan tyckas märklig och elementär, men även den designad för att minimera påverkan av locks 
    vid insert/update/select. På one.com gäller MyISAM storage engine som låser hela tabeller, men klarar samtidiga 
    insert-selects se bl.a https://dev.mysql.com/doc/refman/5.0/en/internal-locking.html för mer info.
    Så bland annat har jag valt att dela upp i separata tabeller per kategori (användaren trycker "spara" i klienten 
    under respektive kategori, och då uppstår ett lock bara på den kategorin - andra användare kan samtidigt manipulera 
    en annan kategori) samt att varje vote är egen kolumn för att unvika tidskrävande selects - 
    utan de ska vara så enkla/snabba som möjligt vid kontroll när röster uppdateras.
    (vs/vstat sidorna får i gengäld lite mer komplexa queries, men dessa utsätts inte för masstrafik)
  * Inga inställningar sparas i SQL utan i _config.php för att hålla ner antal sql-operationer
  

  
  
TODO / ROADMAP:
===============
* strukturera upp kod bättre php/js
* strukturera upp egen css till egna filer
* bygga ut adm.php så att tävling kan sättas upp därifrån (istället för manuellt tabellskapande, _config.php), och att users   kan skapas säkrare den vägen
* vs.php exportmöjligheter
* mer statistiksidor
* möjlighet att lägga in ölnamn (för visning i vs.php samt för röstare i index.php)
* etc...

  


