<?php

return [
    // Common success messages
    'success' => [
        'created'   => ':item erfolgreich erstellt',
        'updated'   => ':item erfolgreich aktualisiert',
        'deleted'   => ':item erfolgreich gelöscht',
        'saved'     => ':item erfolgreich gespeichert',
        'uploaded'  => ':item erfolgreich hochgeladen',
        'toggled'   => ':item erfolgreich umgeschaltet',
        'reordered' => ':items erfolgreich neu geordnet',
    ],

    // Common error messages
    'error' => [
        'generic'           => 'Ein Fehler ist aufgetreten. Bitte versuchen Sie es erneut.',
        'not_found'         => ':item nicht gefunden',
        'unauthorized'      => 'Sie sind nicht berechtigt, diese Aktion durchzuführen.',
        'invalid'           => 'Ungültige :item angegeben',
        'failed'            => ':action von :item fehlgeschlagen',
        'validation'        => 'Validierung fehlgeschlagen',
        'too_many_requests' => 'Zu viele Anfragen. Bitte versuchen Sie es später erneut.',
        'admin_required'    => 'Nicht autorisiert. Administrator-Zugriff erforderlich.',
    ],

    // Media
    'media' => [
        'uploaded'        => 'Medium erfolgreich hochgeladen',
        'deleted'         => 'Medium erfolgreich gelöscht',
        'items_deleted'   => ':count Medienelemente erfolgreich gelöscht',
        'upload_failed'   => 'Hochladen der Datei fehlgeschlagen: :name',
        'delete_failed'   => 'Löschen des Mediums fehlgeschlagen',
        'invalid_upload'  => 'Ungültiger Datei-Upload',
        'caption_updated' => 'Beschriftung erfolgreich aktualisiert',
        'cover_updated'   => 'Titelbild erfolgreich aktualisiert.',
    ],

    // Footer
    'footer' => [
        'section_deleted'    => 'Abschnitt erfolgreich gelöscht',
        'sections_reordered' => 'Abschnitte erfolgreich neu geordnet',
        'widget_created'     => 'Widget erfolgreich erstellt',
        'widget_updated'     => 'Widget erfolgreich aktualisiert',
        'widget_deleted'     => 'Widget erfolgreich gelöscht',
        'widget_toggled'     => 'Widget erfolgreich umgeschaltet',
        'widget_reordered'   => 'Widget-Reihenfolge erfolgreich aktualisiert',
    ],

    // Newsletter
    'newsletter' => [
        'subscribed'        => 'Erfolgreich für Newsletter angemeldet!',
        'subscribe_failed'  => 'Newsletter-Anmeldung fehlgeschlagen',
        'invalid_email'     => 'Ungültige E-Mail-Adresse',
        'disabled'          => 'Newsletter ist derzeit deaktiviert',
        'not_configured'    => 'Newsletter ist nicht richtig konfiguriert',
        'unsupported'       => 'Nicht unterstützter Newsletter-Anbieter',
        'error_occurred'    => 'Ein Fehler ist aufgetreten. Bitte versuchen Sie es später erneut.',
        'try_again'         => 'Anmeldung fehlgeschlagen. Bitte versuchen Sie es erneut.',
        'coming_soon'       => 'ActiveCampaign-Integration kommt bald',
    ],

    // API Keys
    'api_keys' => [
        'created'             => 'API-Schlüssel erfolgreich erstellt. Speichern Sie das Geheimnis - es wird nicht erneut angezeigt!',
        'updated'             => 'API-Schlüssel erfolgreich aktualisiert',
        'deleted'             => 'API-Schlüssel erfolgreich gelöscht',
        'regenerated'         => 'API-Schlüssel erfolgreich neu generiert. Speichern Sie das neue Geheimnis - es wird nicht erneut angezeigt!',
        'disabled'            => 'API-Schlüssel-Funktion ist nicht aktiviert.',
        'max_reached'         => 'Sie können nur bis zu :max API-Schlüssel haben.',
        'auth_disabled'       => 'API-Schlüssel-Authentifizierung ist nicht aktiviert.',
        'auth_required'       => 'API-Schlüssel-Authentifizierung erforderlich.',
        'invalid_key'         => 'Ungültiger API-Schlüssel.',
        'invalid_secret'      => 'Ungültiges API-Geheimnis.',
        'inactive_or_expired' => 'API-Schlüssel ist inaktiv oder abgelaufen.',
        'missing_ability'     => 'API-Schlüssel hat nicht die erforderliche Berechtigung: :ability',
    ],

    // Account
    'account' => [
        'data_exported'       => 'Datenexport erfolgreich generiert',
        'deletion_disabled'   => 'Kontolöschung ist nicht aktiviert. Bitte kontaktieren Sie den Support.',
        'incorrect_password'  => 'Falsches Passwort.',
        'password_incorrect'  => 'Das Passwort ist falsch.',
        'deletion_subject'    => 'Kontolöschungsanfrage - :username',
        'deleted'             => 'Ihr Konto und alle zugehörigen Daten wurden gelöscht.',
    ],

    // OAuth / Social Login
    'oauth' => [
        'invalid_provider'       => 'Ungültiger OAuth-Anbieter',
        'provider_disabled'      => ':provider-Anmeldung ist derzeit deaktiviert',
        'config_error'           => 'OAuth-Konfigurationsfehler. Bitte kontaktieren Sie den Administrator.',
        'auth_failed'            => 'Authentifizierung fehlgeschlagen. Bitte versuchen Sie es erneut.',
        'registration_disabled'  => 'Neue Benutzerregistrierung über OAuth ist derzeit deaktiviert.',
        'email_required'         => 'E-Mail konnte von :provider nicht abgerufen werden. Stellen Sie sicher, dass Ihre E-Mail öffentlich ist, oder versuchen Sie eine andere Anmeldemethode.',
        'error'                  => 'OAuth-Fehler: :error',
        'cannot_disconnect_last' => 'Letzte Authentifizierungsmethode kann nicht getrennt werden. Bitte legen Sie zuerst ein Passwort fest.',
        'provider_not_connected' => 'Anbieter nicht verbunden',
        'disconnected'           => ':provider-Konto erfolgreich getrennt',
    ],

    // Translations
    'translations' => [
        'updated'               => 'Übersetzungen erfolgreich aktualisiert',
        'locale_created'        => 'Sprache erfolgreich erstellt',
        'key_added'             => 'Übersetzungsschlüssel erfolgreich hinzugefügt',
        'key_deleted'           => 'Übersetzungsschlüssel erfolgreich gelöscht',
        'locale_not_found'      => 'Sprache nicht gefunden',
        'file_not_found'        => 'Übersetzungsdatei nicht gefunden',
        'save_failed'           => 'Speichern der Übersetzungen fehlgeschlagen',
        'base_locale_not_found' => 'Basissprache nicht gefunden',
    ],

    // Notifications
    'notifications' => [
        'all_read'    => 'Alle Benachrichtigungen als gelesen markiert.',
        'not_found'   => 'Benachrichtigung existiert nicht.',
        'deleted'     => 'Benachrichtigung wurde erfolgreich gelöscht.',
        'marked_read' => 'Benachrichtigung wurde als gelesen markiert.',
    ],

    // Sandbox (Kollaborativer Editor)
    'sandbox' => [
        'created'              => 'Sandbox erfolgreich erstellt',
        'updated'              => 'Sandbox erfolgreich aktualisiert',
        'deleted'              => 'Sandbox erfolgreich gelöscht',
        'saved'                => 'Änderungen gespeichert',
        'unauthorized'         => 'Du hast keine Berechtigung, auf diese Sandbox zuzugreifen',
        'collaborator_added'   => 'Mitarbeiter erfolgreich hinzugefügt',
        'collaborator_removed' => 'Mitarbeiter erfolgreich entfernt',
        'not_a_collaborator'   => 'Dieser Benutzer ist kein Mitarbeiter dieser Sandbox',
        'cannot_add_owner'     => 'Eigentümer kann nicht als Mitarbeiter hinzugefügt werden',
        'no_invite'            => 'Keine ausstehende Einladung gefunden',
        'invite_accepted'      => 'Einladung angenommen',
        'version_restored'     => 'Version erfolgreich wiederhergestellt',
        'limit_reached'        => 'Du hast dein Sandbox-Limit (:limit) erreicht. Bitte lösche zuerst eine bestehende Sandbox.',
        'collaborator_limit_reached' => 'Diese Sandbox hat das Mitarbeiter-Limit (:limit) erreicht.',
        'version_limit_reached' => 'Diese Sandbox hat das Versions-Limit (:limit) erreicht. Bitte lösche zuerst ältere Versionen.',
    ],

    // Wiki
    'wiki' => [
        'created'          => 'Wiki-Seite erfolgreich erstellt',
        'updated'          => 'Wiki-Seite erfolgreich aktualisiert',
        'create_page'      => 'Wiki-Seite erstellen',
        'category_created' => 'Kategorie erfolgreich erstellt',
        'category_updated' => 'Kategorie erfolgreich aktualisiert',
    ],

    // User
    'user' => [
        'preferences_updated' => 'Einstellungen erfolgreich aktualisiert',
    ],

    // Conversations
    'conversations' => [
        'reply_failed' => 'Antwort konnte nicht erstellt werden',
        'reply_error'  => 'Antwort konnte nicht gespeichert werden. Bitte versuchen Sie es erneut.',
    ],

    // Settings
    'settings' => [
        'updated'                   => 'Einstellungen erfolgreich aktualisiert',
        'logo_uploaded'             => 'Logo erfolgreich hochgeladen',
        'logo_deleted'              => 'Logo erfolgreich gelöscht',
        'logo_upload_failed'        => 'Logo-Upload fehlgeschlagen',
        'logo_delete_failed'        => 'Logo-Löschung fehlgeschlagen',
        'image_uploaded'            => 'Bild erfolgreich hochgeladen',
        'image_upload_failed'       => 'Bild-Upload fehlgeschlagen',
        'image_deleted'             => 'Bild erfolgreich gelöscht',
        'image_delete_failed'       => 'Bild-Löschung fehlgeschlagen',
        'test_email_sent'           => 'Test-E-Mail erfolgreich gesendet an :recipient',
        'test_email_failed'         => 'Test-E-Mail konnte nicht gesendet werden',
        'cache_cleared'             => 'Cache erfolgreich geleert',
        'cache_clear_failed'        => 'Cache konnte nicht geleert werden',
        'pwa_icon_format'           => 'PWA-App-Symbol muss im PNG-, WebP- oder SVG-Format sein. JPEG wird nicht unterstützt.',
        'pwa_icon_dimensions_error' => 'Bildabmessungen konnten nicht gelesen werden.',
        'pwa_icon_min_size'         => 'PWA-App-Symbol muss mindestens 144×144 Pixel groß sein. Ihr Bild ist :size.',
        'pwa_icon_square'           => 'PWA-App-Symbol sollte quadratisch sein. Ihr Bild ist :size. Bitte laden Sie ein quadratisches Bild hoch.',
    ],

    // Homepage Menu
    'menu' => [
        'item_created'  => 'Menüeintrag erfolgreich erstellt',
        'item_updated'  => 'Menüeintrag erfolgreich aktualisiert',
        'item_deleted'  => 'Menüeintrag erfolgreich gelöscht',
        'order_updated' => 'Menüreihenfolge erfolgreich aktualisiert',
    ],

    // Homepage Widget
    'widget' => [
        'created'       => 'Widget erfolgreich erstellt',
        'updated'       => 'Widget erfolgreich aktualisiert',
        'deleted'       => 'Widget erfolgreich gelöscht',
        'toggled'       => 'Widget erfolgreich umgeschaltet',
        'duplicated'    => 'Widget erfolgreich dupliziert',
        'order_updated' => 'Widget-Reihenfolge erfolgreich aktualisiert',
    ],

    // Migrations
    'migrations' => [
        'unknown'         => 'Unbekannte Migration: :key',
        'queued'          => 'Migration in Warteschlange',
        'queued_all'      => 'Migrationen erfolgreich in Warteschlange',
        'batch_not_found' => 'Stapel nicht gefunden',
        'log_not_found'   => 'Migrationsprotokoll nicht gefunden',
        'cancelled'       => 'Vom Benutzer abgebrochen',
        'cancelled_count' => ':count ausstehende Migrationen abgebrochen',
        'deleted_count'   => ':count alte Migrationsprotokolle gelöscht',
        'user_not_found'        => 'Kein registrierter Benutzer „:user" (Benutzername oder E-Mail)',
        'legacy_user_not_found' => 'Kein importierter Inhalt ist „:name" zugeordnet',
        'legacy_assigned'       => 'Inhalte von „:name" wurden :user zugewiesen',
        'claim_exists'          => 'Du hast bereits eine offene Anfrage für dieses alte Konto',
        'claim_created'         => 'Anfrage eingereicht — ein Admin prüft sie und weist dir deine alten Inhalte zu',
    ],

    // SPA
    'spa' => [
        'no_javascript' => 'Es tut uns leid, aber diese Website funktioniert ohne aktiviertes JavaScript nicht richtig. Bitte aktivieren Sie es, um fortzufahren.',
    ],

    // Homepage
    'homepage' => [
        'section_deleted'     => 'Abschnitt erfolgreich gelöscht',
        'sections_reordered'  => 'Abschnitte erfolgreich neu geordnet',
        'image_deleted'       => 'Bild erfolgreich gelöscht',
        'image_upload_failed' => 'Bild-Upload fehlgeschlagen: :error',
        'image_delete_failed' => 'Bild-Löschung fehlgeschlagen: :error',
        'no_image'            => 'Kein Bild zum Löschen',
    ],

    // Events
    'events' => [
        'created'              => 'Event erstellt',
        'deleted'              => 'Event erfolgreich gelöscht',
        'unauthorized'         => 'Nicht autorisiert. Sie haben keine Berechtigung, dieses Event zu ändern.',
        'unauthorized_approve' => 'Nicht autorisiert. Nur Event-Besitzer können Gäste genehmigen.',
        'guest_not_found'      => 'Gast nicht gefunden',
        'guest_updated'        => 'Gästegenehmigung erfolgreich aktualisiert',
    ],

    // Common data operations
    'common' => [
        'invalid_params'       => 'Ungültige Parameter angegeben',
        'invalid_foreign_key'  => 'Ungültiges Fremdschlüsselfeld',
        'model_not_found'      => 'Zugehöriges Modell nicht gefunden',
        'invalid_model_type'   => 'Ungültiger Modelltyp.',
        'item_not_found'       => 'Element nicht gefunden.',
        'fetch_error'          => 'Fehler beim Abrufen der Elemente: :error',
        'item_related'         => 'Element verknüpft',
        'no_data'              => 'Keine Daten gefunden',
        'copy_suffix'          => '(Kopie)',
    ],

    // DataTable
    'datatable' => [
        'no_builder'      => 'Keine Entity-Builder-Methode definiert.',
        'invalid_builder' => 'Entity-Builder ist keine Instanz von Builder.',
        'actions'         => 'Aktionen',
    ],

    // Taxonomy
    'taxonomy' => [
        'no_data' => 'Keine Daten gefunden',
    ],
];
