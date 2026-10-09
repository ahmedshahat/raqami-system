<?php

return [
    // Disabled by default and restricted to an explicit allow-list.
    'enabled' => env('DEMO_RESET_ENABLED', false),
    'legacy_full_database_reset_enabled' => env('LEGACY_FULL_DEMO_RESET_ENABLED', false),
    'business_ids' => array_values(array_filter(array_map(
        'intval',
        explode(',', (string) env('DEMO_RESET_BUSINESS_IDS', '81,92'))
    ))),
    // Optional one-time bootstrap. The existing scheduler creates a snapshot
    // only while it is missing, then stops scheduling this command.
    'bootstrap_business_ids' => array_values(array_filter(array_map(
        'intval',
        explode(',', (string) env('DEMO_RESET_BOOTSTRAP_BUSINESS_IDS', ''))
    ))),
    'time' => env('DEMO_RESET_TIME', '01:00'),
    'timezone' => env('DEMO_RESET_TIMEZONE', 'Africa/Cairo'),
    'snapshot_directory' => storage_path('app/demo-snapshots'),

    // Authentication/session state is invalidated, never restored.
    'excluded_tables' => [
        'notifications', 'oauth_access_tokens',
        'oauth_auth_codes', 'oauth_refresh_tokens', 'password_resets', 'sessions',
    ],

    // Legacy/pivot relations not consistently represented by database FKs.
    'relations' => [
        ['child' => 'account_transactions', 'child_column' => 'account_id', 'parent' => 'accounts'],
        ['child' => 'accounting_accounts_transactions', 'child_column' => 'accounting_account_id', 'parent' => 'accounting_accounts'],
        ['child' => 'accounting_accounts_transactions', 'child_column' => 'acc_trans_mapping_id', 'parent' => 'accounting_acc_trans_mappings'],
        ['child' => 'accounting_accounts_transactions', 'child_column' => 'transaction_id', 'parent' => 'transactions'],
        ['child' => 'accounting_accounts_transactions', 'child_column' => 'transaction_payment_id', 'parent' => 'transaction_payments'],
        ['child' => 'accounting_budgets', 'child_column' => 'accounting_account_id', 'parent' => 'accounting_accounts'],
        ['child' => 'account_transactions', 'child_column' => 'transaction_id', 'parent' => 'transactions'],
        ['child' => 'account_transactions', 'child_column' => 'transaction_payment_id', 'parent' => 'transaction_payments'],
        ['child' => 'categorizables', 'child_column' => 'category_id', 'parent' => 'categories'],
        ['child' => 'discount_variations', 'child_column' => 'discount_id', 'parent' => 'discounts'],
        ['child' => 'discount_variations', 'child_column' => 'variation_id', 'parent' => 'variations'],
        ['child' => 'essentials_document_shares', 'child_column' => 'document_id', 'parent' => 'essentials_documents'],
        ['child' => 'essentials_kb_users', 'child_column' => 'kb_id', 'parent' => 'essentials_kb'],
        ['child' => 'essentials_kb_users', 'child_column' => 'user_id', 'parent' => 'users'],
        ['child' => 'essentials_payroll_group_transactions', 'child_column' => 'transaction_id', 'parent' => 'transactions'],
        ['child' => 'essentials_todos_users', 'child_column' => 'todo_id', 'parent' => 'essentials_to_dos'],
        ['child' => 'essentials_todos_users', 'child_column' => 'user_id', 'parent' => 'users'],
        ['child' => 'essentials_todo_comments', 'child_column' => 'task_id', 'parent' => 'essentials_to_dos'],
        ['child' => 'essentials_user_allowance_and_deductions', 'child_column' => 'user_id', 'parent' => 'users'],
        ['child' => 'essentials_user_allowance_and_deductions', 'child_column' => 'allowance_deduction_id', 'parent' => 'essentials_allowances_and_deductions'],
        ['child' => 'essentials_user_sales_targets', 'child_column' => 'user_id', 'parent' => 'users'],
        ['child' => 'essentials_user_shifts', 'child_column' => 'user_id', 'parent' => 'users'],
        ['child' => 'essentials_user_shifts', 'child_column' => 'essentials_shift_id', 'parent' => 'essentials_shifts'],
        ['child' => 'gym_attendances', 'child_column' => 'contact_id', 'parent' => 'contacts'],
        ['child' => 'gym_health_trackings', 'child_column' => 'contact_id', 'parent' => 'contacts'],
        ['child' => 'gym_member_diets', 'child_column' => 'contact_id', 'parent' => 'contacts'],
        ['child' => 'mfg_recipes', 'child_column' => 'product_id', 'parent' => 'products'],
        ['child' => 'mfg_recipes', 'child_column' => 'variation_id', 'parent' => 'variations'],
        ['child' => 'mfg_recipe_ingredients', 'child_column' => 'mfg_ingredient_group_id', 'parent' => 'mfg_ingredient_groups'],
        ['child' => 'model_has_permissions', 'child_column' => 'model_id', 'parent' => 'users', 'where' => ['model_type' => 'App\\User']],
        ['child' => 'model_has_roles', 'child_column' => 'model_id', 'parent' => 'users', 'where' => ['model_type' => 'App\\User']],
        ['child' => 'product_locations', 'child_column' => 'product_id', 'parent' => 'products'],
        ['child' => 'product_locations', 'child_column' => 'location_id', 'parent' => 'business_locations'],
        ['child' => 'product_racks', 'child_column' => 'product_id', 'parent' => 'products'],
        ['child' => 'res_product_modifier_sets', 'child_column' => 'product_id', 'parent' => 'products'],
        ['child' => 'sell_line_warranties', 'child_column' => 'sell_line_id', 'parent' => 'transaction_sell_lines'],
        ['child' => 'transaction_sell_lines_purchase_lines', 'child_column' => 'sell_line_id', 'parent' => 'transaction_sell_lines'],
        ['child' => 'transaction_sell_lines_purchase_lines', 'child_column' => 'stock_adjustment_line_id', 'parent' => 'stock_adjustment_lines'],
        ['child' => 'transaction_sell_lines_purchase_lines', 'child_column' => 'purchase_line_id', 'parent' => 'purchase_lines'],
        ['child' => 'user_contact_access', 'child_column' => 'user_id', 'parent' => 'users'],
        ['child' => 'user_contact_access', 'child_column' => 'contact_id', 'parent' => 'contacts'],
        ['child' => 'variation_group_prices', 'child_column' => 'variation_id', 'parent' => 'variations'],
        ['child' => 'variation_location_details', 'child_column' => 'product_id', 'parent' => 'products'],
        ['child' => 'variation_location_details', 'child_column' => 'variation_id', 'parent' => 'variations'],
        ['child' => 'zatca_documents', 'child_column' => 'transaction_id', 'parent' => 'transactions'],
    ],
];
