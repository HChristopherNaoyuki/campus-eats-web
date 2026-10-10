<?php
/**
 * Afrikaans Language File
 *
 * Returns the Afrikaans translation table for the application.
 *
 * CORRECTIONS (Version 3.0 - Footer i18n):
 *
 * - Added footer.description and social aria labels
 *   (footer.social_facebook, footer.social_twitter,
 *   footer.social_instagram) so every key used by
 *   includes/footer.php resolves in Afrikaans.
 * - Existing footer keys retained.
 *
 * SOURCE: GUI Assessment and UI/UX Improvement Report.
 *
 * @version 3.0
 */

return array(

    // -------------------------------------------------------------------------
    // Application
    // -------------------------------------------------------------------------

    'app.name'                      => 'Campus Eats',
    'app.tagline'                   => 'Slaan die ry oor. Haal op kampus op.',

    // -------------------------------------------------------------------------
    // Navigation
    // -------------------------------------------------------------------------

    'nav.home'                      => 'Tuis',
    'nav.about'                     => 'Oor ons',
    'nav.services'                  => 'Dienste',
    'nav.faq'                       => 'Gereelde vrae',
    'nav.help'                      => 'Hulpsentrum',
    'nav.dashboard'                 => 'Kontroleskerm',
    'nav.cart'                      => 'Winkelmandjie',
    'nav.orders'                    => 'Bestellings',
    'nav.menu'                      => 'Spyskaart',
    'nav.reports'                   => 'Verslae',
    'nav.users'                     => 'Gebruikers',
    'nav.vendors'                   => 'Verkopers',
    'nav.feedback'                  => 'Terugvoer',
    'nav.settings'                  => 'Instellings',
    'nav.logout'                    => 'Teken uit',

    // -------------------------------------------------------------------------
    // Roles
    // -------------------------------------------------------------------------

    'role.admin'                    => 'Administrateur',
    'role.vendor'                   => 'Verkoper',
    'role.student'                  => 'Student',
    'role.standard'                 => 'Standaard',

    // -------------------------------------------------------------------------
    // Authentication
    // -------------------------------------------------------------------------

    'auth.sign_in'                  => 'Teken in',
    'auth.sign_up'                  => 'Skep rekening',
    'auth.sign_out'                 => 'Teken uit',
    'auth.sign_in_google'           => 'Teken in met Google',
    'auth.sso_not_configured'       => 'Google SSO is nie op hierdie bediener opgestel nie.',
    'auth.email_or_user_id'         => 'E-pos of studentenommer',
    'auth.email_placeholder'        => 'bv. naam@kampus.edu',
    'auth.password'                 => 'Wagwoord',
    'auth.password_placeholder'     => 'Voer u wagwoord in',
    'auth.show_password'            => 'Wys wagwoord',
    'auth.hide_password'            => 'Versteek wagwoord',
    'auth.forgot_password'          => 'Herstel rekening',
    'auth.no_account'               => 'Nuut hier?',
    'auth.return_home'              => 'Terug na tuis',
    'auth.create_account'           => 'Skep rekening',
    'auth.full_name'                => 'Volle naam',
    'auth.username'                 => 'Gebruikersnaam',
    'auth.confirm_password'         => 'Bevestig wagwoord',
    'auth.role'                     => 'Rol',
    'auth.vendor_shop_name'         => 'Winkelnaam',

    // -------------------------------------------------------------------------
    // Login page
    // -------------------------------------------------------------------------

    'login.title'                   => 'Teken in by Campus Eats',
    'login.subtitle'                => 'Voer u kampusbesonderhede in om voort te gaan',
    'login.hint_identifier'         => 'U kan u e-pos, gebruikersnaam of 16-karakter Gebruiker-ID gebruik.',

    // -------------------------------------------------------------------------
    // Registration
    // -------------------------------------------------------------------------

    'register.title'                => 'Skep u Campus Eats-rekening',
    'register.subtitle'             => 'Kies n rol en voltooi die vorm hieronder.',
    'register.first_admin'          => 'U is die eerste gebruiker. Die Admin-rol is beskikbaar.',
    'register.admin_taken'          => 'n Administrateur bestaan reeds. Kies n ander rol.',
    'register.offline'              => 'Die databasis is tydelik onbeskikbaar. U registrasie is in die tou geplaas en sal verwerk word wanneer konnektiwiteit terugkeer.',

    // -------------------------------------------------------------------------
    // Help
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

    'footer.description'            => 'Bestel vooruit by kampusverkopers en haal op wanneer dit u pas. Geen afleweringsfooi, geen wag nie.',
    'footer.quick_links'            => 'Vinnige skakels',
    'footer.account'                => 'Rekening',
    'footer.legal'                  => 'Regtens',
    'footer.privacy'                => 'Privaatheidsbeleid',
    'footer.terms'                  => 'Diensvoorwaardes',
    'footer.contact'                => 'Kontak ondersteuning',
    'footer.copyright'              => 'Campus Eats. Alle regte voorbehou.',
    'footer.social_facebook'        => 'Campus Eats op Facebook',
    'footer.social_twitter'         => 'Campus Eats op Twitter',
    'footer.social_instagram'       => 'Campus Eats op Instagram',

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
    'common.or'                     => 'of',

    // -------------------------------------------------------------------------
    // Errors
    // -------------------------------------------------------------------------

    'error.generic'                 => 'n Fout het voorgekom. Probeer asseblief later weer.',
    'error.csrf'                    => 'Sekuriteitsvalidering het misluk. Verfris die bladsy en probeer weer.',
    'error.required_fields'         => 'Vul asseblief alle verpligte velde in.',
    'error.invalid_credentials'     => 'Ongeldige besonderhede. Probeer asseblief weer.',
    'error.account_suspended'       => 'U rekening is opgeskort. Kontak ondersteuning.',
    'error.account_unverified'      => 'U rekening is nog nie geverifieer nie. Wag asseblief vir administrateurgoedkeuring.',
    'error.vendor_pending'          => 'U verkoperrekening wag op administratiewe goedkeuring.',
    'error.rate_limited'            => 'Te veel mislukte aanmeldpogings. Wag asseblief voordat u weer probeer.',
    'error.password_mismatch'       => 'Wagwoorde stem nie ooreen nie.',
    'error.password_policy'         => 'Wagwoord moet minstens 8 karakters lank wees en ten minste een hoofletter, een syfer en een spesiale simbool bevat.',
    'error.email_exists'            => 'n Rekening met hierdie e-pos bestaan reeds. Teken asseblief in.',

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