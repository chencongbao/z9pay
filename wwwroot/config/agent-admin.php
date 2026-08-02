<?php

return [

    /*
    |--------------------------------------------------------------------------
    | dcat-admin name
    |--------------------------------------------------------------------------
    |
    | This value is the name of dcat-admin, This setting is displayed on the
    | login page.
    |
    */
    'lang_key' => env('AGENT_ADMIN_LANG_KEY', env('ADMIN_LANG_KEY', env('APP_NAME', 'luckypay'))),

    'name' => env('AGENT_ADMIN_NAME', env('ADMIN_NAME', '远方支付')),

    /*
    |--------------------------------------------------------------------------
    | dcat-admin logo
    |--------------------------------------------------------------------------
    |
    | The logo of all admin pages. You can also set it as an image by using a
    | `img` tag, eg '<img src="http://logo-url" alt="Admin logo">'.
    |
    */
    'logo' => env('AGENT_ADMIN_LOGO', env('ADMIN_LOGO', env('ADMIN_NAME', '远方支付'))),

    /*
    |--------------------------------------------------------------------------
    | dcat-admin mini logo
    |--------------------------------------------------------------------------
    |
    | The logo of all admin pages when the sidebar menu is collapsed. You can
    | also set it as an image by using a `img` tag, eg
    | '<img src="http://logo-url" alt="Admin logo">'.
    |
    */
    'logo-mini' => env('AGENT_ADMIN_LOGO', env('ADMIN_LOGO', env('ADMIN_NAME', '远方支付'))),

    /*
    |--------------------------------------------------------------------------
    | dcat-admin favicon
    |--------------------------------------------------------------------------
    |
    */
    'favicon' => null,

    /*
	 |--------------------------------------------------------------------------
	 | User default avatar
	 |--------------------------------------------------------------------------
	 |
	 | Set a default avatar for newly created users.
	 |
	 */
	'default_avatar' => '@admin/images/default-avatar.jpg',

    /*
    |--------------------------------------------------------------------------
    | dcat-admin route settings
    |--------------------------------------------------------------------------
    |
    | The routing configuration of the admin page, including the path prefix,
    | the controller namespace, and the default middleware. If you want to
    | access through the root path, just set the prefix to empty string.
    |
    */
    'route' => [
        'domain' => env('AGENT_ROUTE_DOMAIN'),

        'prefix' => env('AGENT_ADMIN_ROUTE_PREFIX', 'agent-admin'),

        'namespace' => 'App\\AgentAdmin\\Controllers',

        'middleware' => ['agent.admin.no-store', 'web', 'normalize.agent.grid.pagination', 'normalize.agent.grid.query', 'admin', 'set.lang', 'merchant.config', 'check.agent.user.status'],
    ],

    /*
    |--------------------------------------------------------------------------
    | dcat-admin install directory
    |--------------------------------------------------------------------------
    |
    | The installation directory of the controller and routing configuration
    | files of the administration page. The default is `app/Admin`, which must
    | be set before running `artisan admin::install` to take effect.
    |
    */
    'directory' => app_path('AgentAdmin'),

    /*
    |--------------------------------------------------------------------------
    | dcat-admin html title
    |--------------------------------------------------------------------------
    |
    | Html title for all pages.
    |
    */
    'title' => env('AGENT_ADMIN_NAME', env('ADMIN_NAME', '远方支付')),

    /*
    |--------------------------------------------------------------------------
    | Assets hostname
    |--------------------------------------------------------------------------
    |
   */
    'assets_server' => env('ADMIN_ASSETS_SERVER'),

    /*
    |--------------------------------------------------------------------------
    | Access via `https`
    |--------------------------------------------------------------------------
    |
    | If your page is going to be accessed via https, set it to `true`.
    |
    */
    'https' => env('ADMIN_HTTPS', false),

    /*
    |--------------------------------------------------------------------------
    | dcat-admin auth setting
    |--------------------------------------------------------------------------
    |
    | Authentication settings for all admin pages. Include an authentication
    | guard and a user provider setting of authentication driver.
    |
    | You can specify a controller for `login` `logout` and other auth routes.
    |
    */
    'auth' => [
        'enable' => true,

        'controller' => App\AgentAdmin\Controllers\AuthController::class,

        'guard' => 'agent-admin',

        'guards' => [
            'agent-admin' => [
                'driver'   => 'session',
                'provider' => 'agent-admin',
            ],
        ],

        'providers' => [
            'agent-admin' => [
                'driver' => 'eloquent',
                'model'  => \App\Models\AgentUser::class,
            ],
        ],

        // Add "remember me" to login form
        'remember' => true,

        // All method to path like: auth/users/*/edit
        // or specific method to path like: get:auth/users.
        'except' => [
            'auth/login',
            'auth/verify',
            'auth/logout',
            'captcha/get',
            'captcha/check'
        ],

    ],

    'captcha' => [
        'get_per_minute' => 20,
        'check_per_minute' => 60,
    ],

    /*
    |--------------------------------------------------------------------------
    | The global Grid setting
    |--------------------------------------------------------------------------
    */
    'grid' => [

        // The global Grid action display class.
        'grid_action_class' => Dcat\Admin\Grid\Displayers\DropdownActions::class,

        // The global Grid batch action display class.
        'batch_action_class' => Dcat\Admin\Grid\Tools\BatchActions::class,

        // The global Grid pagination display class.
        'paginator_class' => Dcat\Admin\Grid\Tools\Paginator::class,

        // Maximum per-page value accepted from query parameters.
        'per_page_max' => 200,

        // Query fields accepted by each agent Grid route.
        'request_rules' => [
            'merchant-users' => [
                'string' => ['name', 'coder'],
                'int' => ['merchant_user_id', 'agent_user_id'],
                'range' => [],
                'sort' => ['merchant_user_id'],
            ],
            'deposit-orders' => [
                'string' => ['ordernumber', 'order_no'],
                'int' => ['id', 'status', 'payment_id', 'mid'],
                'range' => ['created_at', 'success_time'],
                'sort' => ['id'],
            ],
            'transfer-orders' => [
                'string' => ['ordernumber', 'order_no'],
                'int' => ['id', 'status', 'mid'],
                'decimal' => ['amount', 'actual_amount'],
                'range' => ['created_at', 'success_time'],
                'sort' => ['id'],
            ],
            'settlement-orders' => [
                'string' => ['ordernumber', 'order_no'],
                'int' => ['id', 'status', 'mid'],
                'decimal' => ['amount', 'actual_amount'],
                'range' => ['created_at', 'success_time'],
                'sort' => ['id'],
            ],
            'payment-rates' => [
                'int' => ['merchant_user_id'],
                'range' => [],
                'sort' => [],
            ],
            'balance-logs' => [
                'int' => ['mid', 'type'],
                'range' => [],
                'sort' => ['id'],
            ],
            'reports-merchant-agents' => [
                'scalar' => [],
                'range' => ['date_add'],
                'sort' => ['id'],
            ],
            'report-merchants' => [
                'int' => ['source_id', 'mid'],
                'range' => ['date_add'],
                'sort' => ['id'],
            ],
        ],

        'actions' => [
            'view' => Dcat\Admin\Grid\Actions\Show::class,
            'edit' => Dcat\Admin\Grid\Actions\Edit::class,
            'quick_edit' => Dcat\Admin\Grid\Actions\QuickEdit::class,
            'delete' => Dcat\Admin\Grid\Actions\Delete::class,
            'batch_delete' => Dcat\Admin\Grid\Tools\BatchDelete::class,
        ],

        // The global Grid column selector setting.
        'column_selector' => [
            'store' => Dcat\Admin\Grid\ColumnSelector\SessionStore::class,
            'store_params' => [
                'driver' => 'file',
            ],
        ],
    ],

    /*
    |--------------------------------------------------------------------------
    | dcat-admin helpers setting.
    |--------------------------------------------------------------------------
    */
    'helpers' => [
        'enable' => false,
    ],

    /*
    |--------------------------------------------------------------------------
    | dcat-admin permission setting
    |--------------------------------------------------------------------------
    |
    | Permission settings for all admin pages.
    |
    */
    'permission' => [
        // Whether enable permission.
        'enable' => false,

        // All method to path like: auth/users/*/edit
        // or specific method to path like: get:auth/users.
        'except' => [
            '/',
            'auth/login',
            'auth/logout',
            'auth/setting',
            'captcha/get',
            'captcha/check'
        ],

    ],

    /*
    |--------------------------------------------------------------------------
    | dcat-admin menu setting
    |--------------------------------------------------------------------------
    |
    */
    'menu' => [
        'cache' => [
            // enable cache or not
            'enable' => false,
            'store'  => 'file',
        ],

        // Whether enable menu bind to a permission.
        'bind_permission' => false,

        // Whether enable role bind to menu.
        'role_bind_menu' => false,

        // Whether enable permission bind to menu.
        'permission_bind_menu' => false,

		'default_icon' => 'feather icon-circle',
    ],

    /*
    |--------------------------------------------------------------------------
    | dcat-admin upload setting
    |--------------------------------------------------------------------------
    |
    | File system configuration for form upload files and images, including
    | disk and upload path.
    |
    */
    'upload' => [

        // Disk in `config/filesystem.php`.
        'disk' => 'public',

        // Image and file upload path under the disk above.
        'directory' => [
            'image' => 'images',
            'file'  => 'files',
        ],
    ],

    /*
    |--------------------------------------------------------------------------
    | dcat-admin database settings
    |--------------------------------------------------------------------------
    |
    | Here are database settings for dcat-admin builtin model & tables.
    |
    */
    'database' => [

        // Database connection for following tables.
        'connection' => '',

        // User tables and model.
        'users_table' => 'agent_users',
        'users_model' => \App\Models\AgentUser::class,

        // Role table and model.
        'roles_table' => 'agent_roles',
        'roles_model' => \App\Models\AgentRole::class,

        // Permission table and model.
        'permissions_table' => 'agent_permissions',
        'permissions_model' => \App\Models\AgentPermission::class,

        // Menu table and model.
        'menu_table' => 'agent_menu',
        'menu_model' => \App\Models\AgentMenu::class,

        // Pivot table for table above.
        'role_users_table'       => 'agent_role_users',
        'role_permissions_table' => 'agent_role_permissions',
        'role_menu_table'        => 'agent_role_menu',
        'permission_menu_table'  => 'agent_permission_menu',
        'settings_table'         => 'admin_settings',
        'extensions_table'       => 'admin_extensions',
        'extension_histories_table' => 'admin_extension_histories',
    ],

    /*
    |--------------------------------------------------------------------------
    | Application layout
    |--------------------------------------------------------------------------
    |
    | This value is the layout of admin pages.
    */
    'layout' => [
        // default, blue, blue-light, green
        'color' => 'default',

		// sidebar-separate
        'body_class' => [],

        'horizontal_menu' => false,

        'sidebar_collapsed' => false,

        // light, primary, dark
		'sidebar_style' => 'light',

        'dark_mode_switch' => false,

        // bg-primary, bg-info, bg-warning, bg-success, bg-danger, bg-dark
        'navbar_color' => '',
    ],

    /*
    |--------------------------------------------------------------------------
    | The exception handler class
    |--------------------------------------------------------------------------
    |
    */
    'exception_handler' => Dcat\Admin\Exception\Handler::class,

    /*
    |--------------------------------------------------------------------------
    | Enable default breadcrumb
    |--------------------------------------------------------------------------
    |
    | Whether enable default breadcrumb for every page content.
    */
    'enable_default_breadcrumb' => false
];
