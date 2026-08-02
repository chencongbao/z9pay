<?php

namespace App\Admin\Extensions\Form;

use Dcat\Admin\Form\Field\Password;

class PasswordTool extends Password
{
    protected int $passwordLength = 12;

    protected string $confirmField = 'password_confirmation';

    public function length(int $length): self
    {
        $this->passwordLength = max(8, min(32, $length));

        return $this;
    }

    public function confirmField(string $field): self
    {
        $this->confirmField = $field;

        return $this;
    }

    public function render()
    {
        $this->rules([
            'nullable',
            'min:6',
            'regex:/[A-Z]/',
        ], [
            'min' => '密码至少6位',
            'regex' => '密码至少包含一个大写字母',
        ]);

        $this->help('密码至少6位，且至少包含一个大写字母');

        if ($this->form && $this->form->isEditing()) {
            $this->value('');
            $this->default('');
        }

        $this->attribute('autocomplete', 'new-password');

        $toggleClass = 'password-tool-toggle-'.uniqid();
        $generateClass = 'password-tool-generate-'.uniqid();
        $selector = $this->getElementClassSelector();
        $confirmSelector = '';

        if ($this->form && $confirm = $this->form->field($this->confirmField)) {
            $confirmSelector = $confirm->getElementClassSelector();
            $confirm->attribute('autocomplete', 'new-password');
        }

        $this->append(sprintf(
            '<button type="button" class="btn btn-outline-secondary %s">查看</button><button type="button" class="btn btn-primary %s">强密码</button>',
            $toggleClass,
            $generateClass
        ));

        $confirmName = addslashes($this->confirmField);
        $confirmJs = $confirmSelector
            ? "$('{$confirmSelector}').val(password).trigger('change');"
            : "$('input[name=\"{$confirmName}\"], input[name$=\"[{$confirmName}]\"]').val(password).trigger('change');";
        $length = $this->passwordLength;

        $this->script = <<<JS
(function () {
    var input = $('{$selector}');

    $('.{$toggleClass}').off('click').on('click', function () {
        var currentType = input.attr('type') || 'password';
        input.attr('type', currentType === 'password' ? 'text' : 'password');
        $(this).text(currentType === 'password' ? '隐藏' : '查看');
    });

    $('.{$generateClass}').off('click').on('click', function () {
        function randomChar(chars) {
            return chars.charAt(Math.floor(Math.random() * chars.length));
        }

        function shuffle(array) {
            for (var i = array.length - 1; i > 0; i--) {
                var j = Math.floor(Math.random() * (i + 1));
                var temp = array[i];
                array[i] = array[j];
                array[j] = temp;
            }
            return array;
        }

        var upper = 'ABCDEFGHJKLMNPQRSTUVWXYZ';
        var lower = 'abcdefghijkmnopqrstuvwxyz';
        var number = '23456789';
        var special = '!@#$%&+=';
        var all = upper + lower + number + special;
        var passwordChars = [
            randomChar(upper),
            randomChar(lower),
            randomChar(number),
            randomChar(special)
        ];

        for (var i = passwordChars.length; i < {$length}; i++) {
            passwordChars.push(randomChar(all));
        }

        var password = shuffle(passwordChars).join('');
        input.val(password).attr('type', 'text');
        {$confirmJs}
        $('.{$toggleClass}').text('隐藏');
    });
})();
JS;

        return parent::render();
    }
}
