<?php

namespace App\MerchantAdmin\Actions {
    use Dcat\Admin\Actions\Action;
    use Illuminate\Http\Request;

    class FakeFutureAction extends Action
    {
        public function handle(Request $request)
        {
            return $this->response()->success('should-not-run');
        }
    }
}

namespace App\MerchantAdmin\Form {
    use Dcat\Admin\Widgets\Form;

    class FakeFutureForm extends Form
    {
        public function handle(array $input)
        {
            return $this->response()->success('should-not-run');
        }
    }
}

namespace App\MerchantAdmin\Renderable {
    use Dcat\Admin\Support\LazyRenderable;

    class FakeFutureRenderable extends LazyRenderable
    {
        public function render()
        {
            return 'should-not-render';
        }
    }
}
