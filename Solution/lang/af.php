<?php
/**
 * Afrikaans Language File
 *
 * Returns the Afrikaans translation table for the application.
 *
 * REVIEW NOTICE:
 * The Afrikaans translations in this file were produced by the author of
 * this repository and have not been reviewed by a first-language
 * Afrikaans speaker. They are provided so that the multi-language
 * feature is functional. Before publication or assessment submission,
 * have an Afrikaans speaker read the file and correct any string that
 * does not sound natural. The keys are stable; only the values should
 * be edited.
 *
 * Every key present in Solution/lang/en.php is present here. Keys are
 * kept in the same order so the two files can be compared side by side.
 * A key that is missing here falls back to the English value, which is
 * why completeness matters: an incomplete file is silent.
 *
 * Usage:
 *   echo __('nav.home');            // Tuis
 *   echo __e('auth.sign_in');       // Meld aan (HTML escaped)
 *
 * CORRECTIONS (Version 1.0):
 * - Initial Afrikaans translation table for the multi-language feature.
 * - Mirrors every key in the English table.
 *
 * SOURCE: NOTES - Include multi-language support for at least two
 *         South African languages: English and Afrikaans.
 *
 * @version 1.0
 */

return array(

    // -------------------------------------------------------------------------
    // Application
    // -------------------------------------------------------------------------

    'app.name'                      => 'Campus Eats',
    'app.tagline'                   => 'Slaan die ry oor. Tel op kampus op.',

    // -------------------------------------------------------------------------
    // Navigation
    // -------------------------------------------------------------------------

    'nav.home'                      => 'Tuis',
    'nav.about'                     => 'Oor ons',
    'nav.services'                  => 'Dienste',
    'nav.faq'                       => 'Gereelde vrae',
    'nav.help'                      => 'Hulpsentrum',
    'nav.dashboard'                 => 'Paneelbord',
    'nav.cart'                      => 'Mandjie',
    'nav.language'                  => 'Taal',

    // -------------------------------------------------------------------------
    // Authentication
    // -------------------------------------------------------------------------

    'auth.sign_in'                  => 'Meld aan',
    'auth.sign_out'                 => 'Teken uit',
    'auth.sign_up'                  => 'Skep rekening',
    'auth.email'                    => 'E-pos',
    'auth.email_or_user_id'         => 'Gebruikers-ID, gebruikersnaam of e-pos',
    'auth.email_placeholder'        => '16-karakter gebruikers-ID, gebruikersnaam of e-pos',
    'auth.password'                 => 'Wagwoord',
    'auth.password_placeholder'     => 'Voer u wagwoord in',
    'auth.confirm_password'         => 'Bevestig wagwoord',
    'auth.full_name'                => 'Volle naam',
    'auth.username'                 => 'Gebruikersnaam',
    'auth.role'                     => 'Rol',
    'auth.forgot_password'          => 'Herstel rekening',
    'auth.new_password'             => 'Nuwe wagwoord',
    'auth.reset_password'           => 'Herstel wagwoord',
    'auth.back_to_sign_in'          => 'Terug na aanmelding',
    'auth.return_home'              => 'Keer terug huis toe',
    'auth.already_have_account'     => 'Het u alreeds n rekening?',
    'auth.no_account'               => 'Nuweling hier?',
    'auth.sign_in_google'           => 'Meld aan met Google',
    'auth.sign_up_google'           => 'Registreer met Google',
    'auth.sso_not_configured'       => 'Google SSO is nie op hierdie bediener opgestel nie.',

    // -------------------------------------------------------------------------
    // Roles
    // -------------------------------------------------------------------------

    'role.student'                  => 'Student',
    'role.standard'                 => 'Standaard',
    'role.vendor'                   => 'Verkoper',
    'role.admin'                    => 'Administrateur',
    'role.admin_first_user'         => 'Admin (slegs eerste gebruiker)',

    // -------------------------------------------------------------------------
    // Registration
    // -------------------------------------------------------------------------

    'register.title'                => 'Skep rekening',
    'register.subtitle'             => 'Sluit aan by die kampus-afhaalnetwerk',
    'register.name_placeholder'     => 'U volle naam',
    'register.email_placeholder'    => 'u@kampus.edu',
    'register.password_placeholder' => 'Skep n wagwoord',
    'register.hint_password'        => 'Minstens 8 karakters, sluit hoofletter, syfer en spesiale simbool in.',
    'register.hint_student'         => 'Studente ontvang n afslag van 2,5 persent op bestellings.',
    'register.hint_admin_first'     => 'Die Admin-rol is slegs vir hierdie eerste registrasie beskikbaar.',
    'register.first_user_title'     => 'U is die eerste gebruiker.',
    'register.first_user_body'      => 'Daar bestaan nog geen rekeninge nie. U kan hierdie eerste rekening as n administrateur registreer. Sodra enige rekening bestaan, sal die Admin-rol nie meer aangebied word nie.',
    'register.success_heading'      => 'Rekening suksesvol geskep.',
    'register.success_body'         => 'U 16-karakter GEBRUIKERS-ID is geskep. U kan nou aanmeld.',
    'register.user_id_label'        => 'U 16-karakter GEBRUIKERS-ID',
    'register.user_id_note'         => 'Bewaar hierdie ID. U sal dit nodig hê om u wagwoord te herstel.',
    'register.copy_id'              => 'Kopieer',
    'register.go_to_login'          => 'Gaan na aanmelding',

    // -------------------------------------------------------------------------
    // Login
    // -------------------------------------------------------------------------

    'login.title'                   => 'Meld aan',
    'login.subtitle'                => 'Welkom terug by Campus Eats',
    'login.hint_identifier'         => 'U kan aanmeld met die e-posadres, die gebruikersnaam, of die 16-karakter gebruikers-ID wat by registrasie gewys is.',

    // -------------------------------------------------------------------------
    // Home page
    // -------------------------------------------------------------------------

    'home.hero_title'               => 'Slaan die ry oor.',
    'home.hero_subtitle'            => 'Tel op kampus op.',
    'home.hero_body'                => 'Campus Eats is die afhaalnetwerk op kampus. Bestel vooruit by u gunsteling kampusverkoper en gryp dit op pad na die klas. Geen afleweringsfooi, geen wag.',
    'home.order_now'                => 'Bestel nou',
    'home.learn_more'               => 'Leer meer',
    'home.stat_vendors'             => 'Kampusverkopers',
    'home.stat_items'               => 'Spyskaartitems',
    'home.stat_pickup'              => 'Gemiddelde afhaal',
    'home.how_it_works_title'       => 'Afhaal in drie stappe',
    'home.how_it_works_body'        => 'Ontwerp rondom die kampusritme, tussen lesings, voor oefening, na die biblioteek.',
    'home.step_one_title'           => 'Blaai en bestel',
    'home.step_one_body'            => 'Kies items by enige kampusverkoper en bevestig u bestelling.',
    'home.step_two_title'           => 'Verkoper berei voor',
    'home.step_two_body'            => 'Volg die status soos dit beweeg van Hangende na Voorbereiding na Voltooi.',
    'home.step_three_title'         => 'Tel dit op',
    'home.step_three_body'          => 'Stap na die verkoper se stalletjie en gryp u sak. Klaar.',
    'home.features_title'           => 'Alles wat die stelsel bestuur',
    'home.features_body'            => 'Vier kernmodules, soos in die proses-spesifikasie gedefinieer.',
    'home.feature_user_title'       => 'Gebruikersbestuur',
    'home.feature_user_body'        => 'Registreer en meld aan as Student, Standaard, Verkoper of Administrateur.',
    'home.feature_vendor_title'     => 'Verkoperbestuur',
    'home.feature_vendor_body'      => 'Onthaal kampusverkopers met ligging en kontakbesonderhede.',
    'home.feature_menu_title'       => 'Spyskaartbestuur',
    'home.feature_menu_body'        => 'Voeg by, wysig en verwyder spyskaartitems per verkoper.',
    'home.feature_order_title'      => 'Bestellingsbestuur',
    'home.feature_order_body'       => 'Plaas bestellings en volg Hangende na Voorbereiding na Voltooi.',
    'home.featured_vendor_title'    => 'Uitgeligte verkoper',
    'home.featured_vendor_body'     => 'Ontdek n kampusverkoper. Registreer om alle beskikbare opsies te sien.',
    'home.popular_items'            => 'Gewilde items',
    'home.vendor_signup_cta'        => 'Bestuur u n stalletjie op kampus?',
    'home.vendor_signup_body'       => 'Lys u spyskaart, neem afhaalbestellings en vervul dit met n eenvoudige statuswerkvloei. Verslae vir verkope, verkoperprestasie en gebruikersaktiwiteit ingesluit.',
    'home.become_vendor'            => 'Word n verkoper',

    // -------------------------------------------------------------------------
    // About page
    // -------------------------------------------------------------------------

    'about.title'                   => 'Die span agter Campus Eats',
    'about.subtitle'                => 'Campus Eats is n studente-afhaalplatform wat een produktaal en een bestellingswerkvloei deel.',
    'about.story_heading'           => 'Ons storie',
    'about.offer_heading'           => 'Wat ons bied',
    'about.students_heading'        => 'Vir studente',
    'about.vendors_heading'         => 'Vir verkopers',
    'about.technology_heading'      => 'Ons tegnologie',

    // -------------------------------------------------------------------------
    // FAQ page
    // -------------------------------------------------------------------------

    'faq.title'                     => 'Gereelde vrae',
    'faq.subtitle'                  => 'Antwoorde op die vrae wat studente en verkopers die meeste vra.',

    // -------------------------------------------------------------------------
    // Help page
    // -------------------------------------------------------------------------

    'help.title'                    => 'Hulpsentrum',
    'help.subtitle'                 => 'Vind antwoorde op u vrae en leer hoe om Campus Eats te gebruik.',
    'help.getting_started'          => 'Aan die begin',
    'help.student_help'             => 'Studentehulp',
    'help.vendor_help'              => 'Verkoperhulp',
    'help.troubleshooting'          => 'Probleemoplossing',
    'help.contact'                  => 'Kontak ondersteuning',

    // -------------------------------------------------------------------------
    // Footer
    // -------------------------------------------------------------------------

    'footer.quick_links'            => 'Vinnige skakels',
    'footer.account'                => 'Rekening',
    'footer.legal'                  => 'Regtens',
    'footer.privacy'                => 'Privaatheidsbeleid',
    'footer.terms'                  => 'Diensvoorwaardes',
    'footer.contact'                => 'Kontak ondersteuning',
    'footer.copyright'              => 'Campus Eats. Alle regte voorbehou.',

    // -------------------------------------------------------------------------
    // Common
    // -------------------------------------------------------------------------

    'common.loading'                => 'Laai tans...',
    'common.save'                   => 'Stoor',
    'common.cancel'                 => 'Kanselleer',
    'common.close'                  => 'Sluit',
    'common.confirm'                => 'Bevestig',
    'common.delete'                 => 'Verwyder',
    'common.edit'                   => 'Wysig',
    'common.back'                   => 'Terug',
    'common.next'                   => 'Volgende',
    'common.search'                 => 'Soek',
    'common.submit'                 => 'Dien in',

    // -------------------------------------------------------------------------
    // Errors
    // -------------------------------------------------------------------------

    'error.generic'                 => 'n Fout het voorgekom. Probeer asseblief later weer.',
    'error.csrf'                    => 'Sekuriteitskontrole het misluk. Herlaai die bladsy en probeer weer.',
    'error.required_fields'         => 'Vul asseblief alle verpligte velde in.',
    'error.invalid_email'           => 'Voer asseblief n geldige e-posadres in.',
    'error.invalid_credentials'     => 'Ongeldige e-pos, gebruikersnaam of wagwoord.',
    'error.account_suspended'       => 'U rekening is opgeskort. Kontak asseblief n administrateur.',
    'error.account_unverified'      => 'U rekening is nog nie geverifieer nie. Wag asseblief vir administratiewe goedkeuring.',
    'error.vendor_pending'          => 'U verkoperrekening wag op administratiewe goedkeuring.',
    'error.rate_limited'            => 'Te veel mislukte aanmeldpogings. Wag asseblief voordat u weer probeer.',
    'error.password_mismatch'       => 'Wagwoorde stem nie ooreen nie.',
    'error.password_policy'         => 'Wagwoord moet minstens 8 karakters lank wees en ten minste een hoofletter, een syfer en een spesiale simbool bevat.',
    'error.email_exists'            => 'n Rekening met hierdie e-pos bestaan reeds. Meld asseblief aan.',

    // -------------------------------------------------------------------------
    // Success
    // -------------------------------------------------------------------------

    'success.registered'            => 'Rekening suksesvol geskep. U kan nou aanmeld.',
    'success.password_reset'        => 'U wagwoord is suksesvol herstel. U kan nou met u nuwe wagwoord aanmeld.',

    // -------------------------------------------------------------------------
    // Feedback
    // -------------------------------------------------------------------------

    'feedback.title'                => 'Dien terugvoer in',
    'feedback.subtitle'             => 'Deel u ervaring met ons.',
    'feedback.type'                 => 'Tipe terugvoer',
    'feedback.type_complaint'       => 'Klagte: rapporteer n probleem',
    'feedback.type_compliment'      => 'Kompliment: deel iets positiefs',
    'feedback.subject'              => 'Onderwerp',
    'feedback.message'              => 'Boodskap',
    'feedback.submit'               => 'Dien terugvoer in',
    'feedback.success'              => 'U terugvoer is suksesvol ingedien. n Administrateur sal dit hersien.',

);