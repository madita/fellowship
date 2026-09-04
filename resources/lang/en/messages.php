<?php

return [
    // Common success messages
    'success' => [
        'created'   => ':item created successfully',
        'updated'   => ':item updated successfully',
        'deleted'   => ':item deleted successfully',
        'saved'     => ':item saved successfully',
        'uploaded'  => ':item uploaded successfully',
        'toggled'   => ':item toggled successfully',
        'reordered' => ':items reordered successfully',
    ],

    // Common error messages
    'error' => [
        'generic'           => 'An error occurred. Please try again.',
        'not_found'         => ':item not found',
        'unauthorized'      => 'You are not authorized to perform this action.',
        'invalid'           => 'Invalid :item provided',
        'failed'            => 'Failed to :action :item',
        'validation'        => 'Validation failed',
        'too_many_requests' => 'Too many requests. Please try again later.',
        'admin_required'    => 'Unauthorized. Admin access required.',
    ],

    // Media
    'media' => [
        'uploaded'        => 'Media uploaded successfully',
        'deleted'         => 'Media deleted successfully',
        'items_deleted'   => ':count media items deleted successfully',
        'upload_failed'   => 'Failed to upload file: :name',
        'delete_failed'   => 'Failed to delete media',
        'invalid_upload'  => 'Invalid file upload',
        'caption_updated' => 'Caption updated successfully',
        'cover_updated'   => 'Cover image updated successfully.',
    ],

    // Footer
    'footer' => [
        'section_deleted'    => 'Section deleted successfully',
        'sections_reordered' => 'Sections reordered successfully',
        'widget_created'     => 'Widget created successfully',
        'widget_updated'     => 'Widget updated successfully',
        'widget_deleted'     => 'Widget deleted successfully',
        'widget_toggled'     => 'Widget toggled successfully',
        'widget_reordered'   => 'Widget order updated successfully',
    ],

    // Homepage
    'homepage' => [
        'section_deleted'     => 'Section deleted successfully',
        'sections_reordered'  => 'Sections reordered successfully',
        'image_deleted'       => 'Image deleted successfully',
        'image_upload_failed' => 'Failed to upload image: :error',
        'image_delete_failed' => 'Failed to delete image: :error',
        'no_image'            => 'No image to delete',
    ],

    // Events
    'events' => [
        'created'              => 'Event created',
        'deleted'              => 'Event deleted successfully',
        'unauthorized'         => 'Unauthorized. You do not have permission to modify this event.',
        'unauthorized_approve' => 'Unauthorized. Only event owners can approve guests.',
        'guest_not_found'      => 'Guest not found',
        'guest_updated'        => 'Guest approval updated successfully',
    ],

    // Newsletter
    'newsletter' => [
        'subscribed'        => 'Successfully subscribed to newsletter!',
        'subscribe_failed'  => 'Failed to subscribe to newsletter',
        'invalid_email'     => 'Invalid email address',
        'disabled'          => 'Newsletter is currently disabled',
        'not_configured'    => 'Newsletter is not properly configured',
        'unsupported'       => 'Unsupported newsletter provider',
        'error_occurred'    => 'An error occurred while subscribing. Please try again later.',
        'try_again'         => 'Failed to subscribe. Please try again.',
        'coming_soon'       => 'ActiveCampaign integration coming soon',
    ],

    // API Keys
    'api_keys' => [
        'created'             => 'API key created successfully. Save the secret - it will not be shown again!',
        'updated'             => 'API key updated successfully',
        'deleted'             => 'API key deleted successfully',
        'regenerated'         => 'API key regenerated successfully. Save the new secret - it will not be shown again!',
        'disabled'            => 'API keys feature is not enabled.',
        'max_reached'         => 'You can only have up to :max API keys.',
        'auth_disabled'       => 'API key authentication is not enabled.',
        'auth_required'       => 'API key authentication required.',
        'invalid_key'         => 'Invalid API key.',
        'invalid_secret'      => 'Invalid API secret.',
        'inactive_or_expired' => 'API key is inactive or expired.',
        'missing_ability'     => 'API key does not have the required ability: :ability',
    ],

    // Account
    'account' => [
        'data_exported'       => 'Data export generated successfully',
        'deletion_disabled'   => 'Account deletion is not enabled. Please contact support.',
        'incorrect_password'  => 'Incorrect password.',
        'password_incorrect'  => 'The password is incorrect.',
        'deletion_subject'    => 'Account Deletion Request - :username',
        'deleted'             => 'Your account and all associated data have been deleted.',
    ],

    // OAuth / Social Login
    'oauth' => [
        'invalid_provider'       => 'Invalid OAuth provider',
        'provider_disabled'      => ':provider login is currently disabled',
        'config_error'           => 'OAuth configuration error. Please contact administrator.',
        'email_required'         => 'Unable to get email from :provider. Please ensure your email is public or try another login method.',
        'auth_failed'            => 'Authentication failed. Please try again.',
        'registration_disabled'  => 'New user registration via OAuth is currently disabled. Please create an account first or contact an administrator.',
        'error'                  => 'OAuth error: :error',
        'cannot_disconnect_last' => 'Cannot disconnect last authentication method. Please set a password first.',
        'provider_not_connected' => 'Provider not connected',
        'disconnected'           => ':provider account disconnected successfully',
    ],

    // Common data operations
    'common' => [
        'invalid_params'       => 'Invalid parameters provided',
        'invalid_foreign_key'  => 'Invalid foreign key field',
        'model_not_found'      => 'Related model not found',
        'invalid_model_type'   => 'Invalid model type.',
        'item_not_found'       => 'Item not found.',
        'fetch_error'          => 'An error occurred while fetching the items: :error',
        'item_related'         => 'Item related',
        'no_data'              => 'No data found',
        'copy_suffix'          => '(Copy)',
    ],

    // DataTable
    'datatable' => [
        'no_builder'      => 'No entity builder method defined.',
        'invalid_builder' => 'Entity builder not instance of Builder.',
        'actions'         => 'Actions',
    ],

    // Taxonomy
    'taxonomy' => [
        'no_data' => 'No data found',
    ],

    // Translations
    'translations' => [
        'updated'               => 'Translations updated successfully',
        'locale_created'        => 'Locale created successfully',
        'key_added'             => 'Translation key added successfully',
        'key_deleted'           => 'Translation key deleted successfully',
        'locale_not_found'      => 'Locale not found',
        'file_not_found'        => 'Translation file not found',
        'save_failed'           => 'Failed to save translations',
        'base_locale_not_found' => 'Base locale not found',
    ],

    // Notifications
    'notifications' => [
        'all_read'    => 'All notifications marked as read.',
        'not_found'   => 'Notification does not exist.',
        'deleted'     => 'Notification was deleted successfully.',
        'marked_read' => 'Notification was marked as read.',
    ],

    // Sandbox (Collaborative Editor)
    'sandbox' => [
        'created'              => 'Sandbox created successfully',
        'updated'              => 'Sandbox updated successfully',
        'deleted'              => 'Sandbox deleted successfully',
        'saved'                => 'Changes saved',
        'unauthorized'         => 'You do not have permission to access this sandbox',
        'collaborator_added'   => 'Collaborator added successfully',
        'collaborator_removed' => 'Collaborator removed successfully',
        'not_a_collaborator'   => 'This user is not a collaborator on this sandbox',
        'cannot_add_owner'     => 'Cannot add owner as a collaborator',
        'no_invite'            => 'No pending invite found',
        'invite_accepted'      => 'Invitation accepted',
        'version_restored'     => 'Version restored successfully',
        'limit_reached'        => 'You have reached your sandbox limit (:limit). Please delete an existing sandbox first.',
        'collaborator_limit_reached' => 'This sandbox has reached the collaborator limit (:limit).',
        'version_limit_reached' => 'This sandbox has reached the version limit (:limit). Please delete older versions first.',
    ],

    // Wiki
    'wiki' => [
        'created'          => 'Wiki page created successfully',
        'updated'          => 'Wiki page updated successfully',
        'create_page'      => 'Create Wiki page',
        'category_created' => 'Category created successfully',
        'category_updated' => 'Category updated successfully',
    ],

    // User
    'user' => [
        'preferences_updated' => 'Preferences updated successfully',
    ],

    // Conversations
    'conversations' => [
        'reply_failed' => 'Failed to create reply',
        'reply_error'  => 'Unable to save reply. Please try again.',
    ],

    // Settings
    'settings' => [
        'updated'                   => 'Settings updated successfully',
        'logo_uploaded'             => 'Logo uploaded successfully',
        'logo_deleted'              => 'Logo deleted successfully',
        'logo_upload_failed'        => 'Failed to upload logo',
        'logo_delete_failed'        => 'Failed to delete logo',
        'image_uploaded'            => 'Image uploaded successfully',
        'image_upload_failed'       => 'Failed to upload image',
        'image_deleted'             => 'Image deleted successfully',
        'image_delete_failed'       => 'Failed to delete image',
        'test_email_sent'           => 'Test email sent successfully to :recipient',
        'test_email_failed'         => 'Failed to send test email',
        'cache_cleared'             => 'Cache cleared successfully',
        'cache_clear_failed'        => 'Failed to clear cache',
        'pwa_icon_format'           => 'PWA app icon must be PNG, WebP, or SVG format. JPEG is not supported.',
        'pwa_icon_dimensions_error' => 'Unable to read image dimensions.',
        'pwa_icon_min_size'         => 'PWA app icon must be at least 144×144 pixels. Your image is :size.',
        'pwa_icon_square'           => 'PWA app icon should be square. Your image is :size. Please upload a square image.',
    ],

    // Homepage Menu
    'menu' => [
        'item_created'  => 'Menu item created successfully',
        'item_updated'  => 'Menu item updated successfully',
        'item_deleted'  => 'Menu item deleted successfully',
        'order_updated' => 'Menu order updated successfully',
    ],

    // Homepage Widget
    'widget' => [
        'created'       => 'Widget created successfully',
        'updated'       => 'Widget updated successfully',
        'deleted'       => 'Widget deleted successfully',
        'toggled'       => 'Widget toggled successfully',
        'duplicated'    => 'Widget duplicated successfully',
        'order_updated' => 'Widget order updated successfully',
    ],

    // Migrations
    'migrations' => [
        'unknown'         => 'Unknown migration: :key',
        'queued'          => 'Migration queued',
        'queued_all'      => 'Migrations queued successfully',
        'batch_not_found' => 'Batch not found',
        'log_not_found'   => 'Migration log not found',
        'cancelled'       => 'Cancelled by user',
        'cancelled_count' => 'Cancelled :count pending migrations',
        'deleted_count'   => 'Deleted :count old migration logs',
        'user_not_found'        => 'No registered user ":user" (username or e-mail)',
        'legacy_user_not_found' => 'No imported content is attributed to ":name"',
        'legacy_assigned'       => 'Content of ":name" assigned to :user',
        'claim_exists'          => 'You already have an open claim for this legacy account',
        'claim_created'         => 'Claim submitted — an admin will review it and assign your old content',
    ],

    // SPA
    'spa' => [
        'no_javascript' => "We're sorry but this website doesn't work properly without JavaScript enabled. Please enable it to continue.",
    ],
];
