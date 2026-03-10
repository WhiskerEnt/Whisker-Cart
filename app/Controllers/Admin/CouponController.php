<?php
namespace App\Controllers\Admin;
use Core\{Request, View, Database, Response, Session, Validator};

class CouponController
{
    public function index(Request $request, array $params = []): void
    {
        $coupons = Database::fetchAll("SELECT * FROM wk_coupons ORDER BY created_at DESC");
        View::render('admin/coupons/index', ['pageTitle'=>'Coupons','coupons'=>$coupons], 'admin/layouts/main');
    }

    public function create(Request $request, array $params = []): void
    {
        View::render('admin/coupons/create', ['pageTitle'=>'Create Coupon'], 'admin/layouts/main');
    }

    public function store(Request $request, array $params = []): void
    {
        if (!Session::verifyCsrf($request->input('wk_csrf'))) {
            Session::flash('error','Session expired.');
            Response::redirect(View::url('admin/coupons/create'));
            return;
        }
        $v = new Validator($request->all(), [
            'code'=>'required|max:50', 'type'=>'required|in:percentage,fixed', 'value'=>'required|numeric|min:0',
        ]);
        if ($v->fails()) { Session::flash('error',$v->firstError()); Response::redirect(View::url('admin/coupons/create')); return; }

        Database::insert('wk_coupons', [
            'code'=>strtoupper($request->clean('code')),
            'type'=>$request->clean('type'),
            'value'=>(float)$request->input('value'),
            'min_order_amount'=>(float)($request->input('min_order_amount')??0),
            'max_discount'=>$request->input('max_discount')?(float)$request->input('max_discount'):null,
            'usage_limit'=>$request->input('usage_limit')?(int)$request->input('usage_limit'):null,
            'expires_at'=>$request->input('expires_at')?:null,
            'is_active'=>1,
        ]);
        Session::flash('success','Coupon created!');
        Response::redirect(View::url('admin/coupons'));
    }

    public function delete(Request $request, array $params = []): void
    {
        if (!Session::verifyCsrf($request->input('wk_csrf'))) {
            Session::flash('error', 'Session expired.');
            Response::redirect(View::url('admin/coupons'));
            return;
        }
        Database::delete('wk_coupons','id=?',[$params['id']]);
        Session::flash('success','Coupon deleted.');
        Response::redirect(View::url('admin/coupons'));
    }
}
