@if($google_two_fa_enable == 1)
    <span class="label" style="background-color: {{\Dcat\Admin\Admin::color()->green()}}">{{admin_trans_option(1,"status_text")}}</span>
@else
    <span class="label" style="background-color: {{\Dcat\Admin\Admin::color()->red()}}">{{admin_trans_option(0,"status_text")}}</span>
@endif
@if($google_two_fa_bind == 1)
    <span class="label" style="background-color: {{\Dcat\Admin\Admin::color()->green()}}">{{admin_trans_option(1,"bind_status_text")}}</span>
@else
    <span class="label" style="background-color: {{\Dcat\Admin\Admin::color()->red()}}">{{admin_trans_option(0,"bind_status_text")}}</span>
@endif
