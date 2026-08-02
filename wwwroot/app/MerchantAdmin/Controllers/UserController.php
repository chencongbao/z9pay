<?php

namespace App\MerchantAdmin\Controllers;

use Dcat\Admin\Form;
use Dcat\Admin\Grid;
use Dcat\Admin\Admin;
use App\Traits\AdminTrait;
use App\Models\MerchantInfo;
use App\Models\MerchantUser;
use Dcat\Admin\Layout\Content;
use PragmaRX\Google2FA\Google2FA;
use Dcat\Admin\Http\Auth\Permission;
use App\MerchantAdmin\Actions\User\Delete;
use App\Admin\Controllers\CommonController;
use App\Services\IpWhite\WhiteIpFormatService;
use App\Repositories\MerchantUser as Administrator;
use App\MerchantAdmin\Actions\User\UnlockLogin;
use App\MerchantAdmin\Actions\User\ResetGooglePassword;

class UserController extends CommonController
{
    use AdminTrait;

    public $translation = 'merchantuser';

    public function title(): string
    {
        return __('menu.titles.merchant_users');
    }

    public function create(Content $content)
    {
        $this->authorizeMainAccount();

        return parent::create($content);
    }

    public function store()
    {
        $this->authorizeMainAccount();

        return parent::store();
    }

    public function edit($id, Content $content)
    {
        $this->authorizeMainAccount();
        $this->authorizeSubAccount($id);

        return parent::edit($id, $content);
    }

    public function update($id)
    {
        $this->authorizeMainAccount();
        $this->authorizeSubAccount($id);

        return parent::update($id);
    }

    protected function grid(): Grid
    {
        $mid = bob_merchant_user_pid();
        $merchantCode = optional(MerchantInfo::query()->whereKey($mid)->first(['coder']))->coder;
        $isMerchantChildAccount = intval(optional(Admin::user())->pid) > 0;

        return Grid::make(Administrator::with(['roles']), function (Grid $grid) use ($mid, $merchantCode, $isMerchantChildAccount) {
            $grid->model()->where('pid', $mid)->orderByDesc('id');
            $grid->column('id')->sortable();
            $grid->column('merchant_code', admin_trans_field('merchant_coder'))->display(function () use ($merchantCode) {
                return $merchantCode;
            });
            $grid->column('username', admin_trans_field('username'));
            $grid->column('name', admin_trans_field('name'));
            $grid->column('roles')->pluck('name')->label('primary');
            $grid->column('status', admin_trans_label('status'))->status();
            $grid->column('google', admin_trans_field('google_validator'))->google();
            $grid->column('login_white_ip', admin_trans_field('login_white_ip'))->display(function ($value) {
                if (empty($value)) {
                    return null;
                }

                return bob_show_table_info(collect(bob_format_muti_data_to_array($value))->map(function ($item) {
                    return [$item];
                })->all());
            });
            $grid->column('last_login_ip', admin_trans_field('login_ip'));
            $grid->column('last_login_time', admin_trans_field('last_login_time'));
            $grid->column('created_at', admin_trans_field('created_at'));
            $grid->disableDeleteButton();
            if ($isMerchantChildAccount) {
                $grid->disableCreateButton();
            } else {
                $grid->enableDialogCreate();
                $grid->disableEditButton();
                $grid->showQuickEditButton();
            }
            $grid->actions(function (Grid\Displayers\Actions $actions) use ($isMerchantChildAccount) {
                if ($isMerchantChildAccount) {
                    $actions->disableEdit();
                    $actions->disableQuickEdit();
                    return;
                }
                if ($actions->row['pid'] > 0) {
                    $actions->append(new Delete());
                    $actions->append(new UnlockLogin());
                }
                $actions->append(new ResetGooglePassword());
            });
        });
    }

    public function form(): Form
    {
        $loginWhiteIpRule = $this->loginWhiteIpRule();
        $merchantRoleRule = $this->merchantRoleRule();
        $messages = $this->validationMessages();

        return Form::make(Administrator::with(['roles']), function (Form $form) use ($loginWhiteIpRule, $merchantRoleRule, $messages) {
            $userTable = config('merchant-admin.database.users_table');
            $connection = config('merchant-admin.database.connection');
            $id = $form->getKey();

            if ($id) {
                $form->display('username', admin_trans_field('username'));
                $form->password('password', trans('admin.password'))
                    ->rules(['nullable', 'min:5', 'max:20'], [
                        'min' => $messages['password_min'],
                        'max' => $messages['password_max'],
                    ])
                    ->minLength(5)
                    ->maxLength(20)
                    ->customFormat(function () {
                        return '';
                    });
            } else {
                $form->hidden('pid')->default(0);
                $form->text('username', admin_trans_field('username'))
                    ->required()
                    ->creationRules(['required', "unique:{$connection}.{$userTable}"], [
                        'required' => $messages['username_required'],
                        'unique' => $messages['username_unique'],
                    ])
                    ->updateRules(['required', "unique:{$connection}.{$userTable},username,$id"], [
                        'required' => $messages['username_required'],
                        'unique' => $messages['username_unique'],
                    ]);
                $form->password('password', trans('admin.password'))
                    ->required()
                    ->rules(['required', 'min:5', 'max:20'], [
                        'required' => $messages['password_required'],
                        'min' => $messages['password_min'],
                        'max' => $messages['password_max'],
                    ])
                    ->minLength(5)
                    ->maxLength(20);
            }
            $form->password('password_confirmation', trans('admin.password_confirmation'))->same('password', $messages['password_confirm_same']);

            $form->ignore(['password_confirmation']);
            $form->text('name', admin_trans_field('name'))->rules(['required'], ['required' => $messages['name_required']])->required();
            $form->select('roles', trans('admin.roles'))->options(function () {
                $roleModel = config('merchant-admin.database.roles_model');
                return $roleModel::query()->where('id', 2)->orWhere('mid', bob_merchant_user_pid())->pluck('name', 'id');
            })->customFormat(function ($v) {
                return collect(array_column($v, 'id'))->first();
            })->disableClearButton()->rules(['required', 'numeric', 'min:1', $merchantRoleRule], [
                'required' => $messages['role_required'],
                'numeric' => $messages['role_required'],
                'min' => $messages['role_required'],
            ])->required();
            $form->radio('status', admin_trans_label('status'))->options([0 => admin_trans_option(0, 'status_text'), 1 => admin_trans_option(1, 'status_text')])->default(1)->rules(['required', 'in:0,1'], [
                'required' => $messages['status_required'],
                'in' => $messages['status_required'],
            ])->required();
            $form->textarea('login_white_ip', admin_trans_field('login_white_ip'))->rules([$loginWhiteIpRule])->help(admin_trans_field('login_white_ip_validate'));
        })->saving(function (Form $form) {
            $form->pid = bob_merchant_user_pid();
            if ($form->isCreating()) {
                $form->status = 1;
            }

            // 保存前统一校验和格式化登录白名单，避免非法 IP 进入登录校验链路。
            try {
                $form->input('login_white_ip', app(WhiteIpFormatService::class)->normalize($form->login_white_ip, admin_trans_field('login_white_ip')));
            } catch (\Throwable $e) {
                return $form->response()->error($e->getMessage());
            }

            if ($form->password && $form->model()->get('password') != $form->password) {
                $form->password = bcrypt($form->password);
            }
            if (!$form->password) {
                $form->deleteInput('password');
            }
        })->saved(function (Form $form, $result) {
            if ($form->isCreating() && $result) {
                MerchantUser::query()->whereKey($form->repository()->model()->id)->update(['google_two_fa_secret' => (new Google2FA())->generateSecretKey(32)]);
            }
        });
    }

    private function loginWhiteIpRule(): \Closure
    {
        return function ($attribute, $value, $fail) {
            $whiteIpFormatService = app(WhiteIpFormatService::class);
            foreach (bob_format_muti_data_to_array($value) as $ip) {
                if (!$whiteIpFormatService->isValidWhiteIpItem((string)$ip)) {
                    $fail(trans('merchantuser.fields.login_white_ip_invalid', ['ip' => $ip]));
                    return;
                }
            }
        };
    }

    private function merchantRoleRule(): \Closure
    {
        return function ($attribute, $value, $fail) {
            if (!is_numeric($value)) {
                return;
            }

            $roleModel = config('merchant-admin.database.roles_model');
            $exists = $roleModel::query()
                ->whereKey((int)$value)
                ->where(function ($query) {
                    $query->where('id', 2)->orWhere('mid', bob_merchant_user_pid());
                })
                ->exists();

            if (!$exists) {
                $fail(admin_trans_field('role_invalid'));
            }
        };
    }

    private function validationMessages(): array
    {
        return [
            'username_required' => admin_trans_field('username_required'),
            'username_unique' => admin_trans_field('username_unique'),
            'password_required' => admin_trans_field('password_required'),
            'password_min' => admin_trans_field('password_min'),
            'password_max' => admin_trans_field('password_max'),
            'password_confirm_same' => admin_trans_field('password_confirm_same'),
            'name_required' => admin_trans_field('name_required'),
            'role_required' => admin_trans_field('role_required'),
            'status_required' => admin_trans_field('status_required'),
        ];
    }

    private function authorizeMainAccount(): void
    {
        if (intval(optional(Admin::user())->pid) > 0) {
            Permission::error();
        }
    }

    private function authorizeSubAccount($id): void
    {
        $exists = MerchantUser::query()
            ->whereKey($id)
            ->where('pid', bob_merchant_user_pid())
            ->exists();

        if (!$exists) {
            Permission::error();
        }
    }
}
