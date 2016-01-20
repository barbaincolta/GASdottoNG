<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;

use App\Http\Requests;
use App\Http\Controllers\Controller;

use DB;
use Auth;
use Theme;

use App\Notification;
use App\User;
use App\Order;

class NotificationsController extends Controller
{
	public function __construct()
	{
		$this->middleware('auth');
	}

	public function index()
	{
		$user = Auth::user();
		if ($user->gas->userCan('notifications.admin') == false)
			$data['notifications'] = Notification::orderBy('start_date', 'desc')->take(20)->get();
		else
			$data['notifications'] = $user->allnotifications;

		return Theme::view('pages.notifications', $data);
	}

	public function store(Request $request)
	{
		DB::beginTransaction();

		$user = Auth::user();
		if ($user->gas->userCan('notifications.admin') == false)
			return $this->errorResponse('Non autorizzato');

		/*
			TODO: gran parte di questo codice dovrà essere spostato
			direttamente nel modello Notification, o in un comando
			dedicato, per essere facilmente riutilizzato altrove
			(e.g. creando le notifiche di riepilogo ordini)
		*/
		$n = new Notification();
		$n->creator_id = $user->id;
		$n->content = $request->input('content');
		$n->mailed = $request->has('mailed');
		$n->start_date = $this->decodeDate($request->input('start_date'));
		$n->end_date = $this->decodeDate($request->input('end_date'));
		$n->save();

		$users = $request->input('users', []);
		if (empty($users)) {
			$us = User::get();
			foreach ($us as $u)
				$users[] = $u->id;
		}
		else {
			$map = [];

			foreach ($users as $u) {
				if (strrpos($u, 'special::', -strlen($u)) !== FALSE) {
					if ($u == 'special::referrers') {
						$us = User::get();
						foreach ($us as $u)
							if ($user->gas->userHas('supplier.add|supplier.modify'))
								$map[] = $u->id;
					}
					else if (strrpos($u, 'special::order::', -strlen($u)) !== FALSE) {
						$order_id = substr($u, strlen('special::order::'));
						$order = Order::findOrFail($order_id);
						foreach ($order->bookings as $booking)
							$map[] = $booking->user->id;
					}
				}
				else {
					$map[] = $u;
				}
			}

			$users = $map;
		}

		$n->users()->sync($users, ['done' => false]);

		return $this->successResponse([
			'id' => $n->id,
			'name' => $n->printableName(),
			'header' => $n->printableHeader(),
			'url' => url('notifications/' . $n->id)
		]);
	}

	public function show($id)
	{
		$n = Notification::findOrFail($id);

		$user = Auth::user();
		if ($user->gas->userCan('notifications.admin') == false && $n->hasUser($user) == false)
			return $this->errorResponse('Non autorizzato');

		return Theme::view('notification.show', ['notification' => $n]);
	}

	public function markread($id)
	{
		DB::beginTransaction();

		$user = Auth::user();
		$n = Notification::findOrFail($id);

		if ($n->hasUser($user)) {
			$n->users()->where('user_id', '=', $user->id)->withPivot('done')->update(['done' => true]);
			return $this->successResponse();
		}
		else {
			return $this->errorResponse('Non autorizzato');
		}
	}
}