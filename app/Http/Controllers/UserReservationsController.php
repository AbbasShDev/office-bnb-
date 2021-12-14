<?php

namespace App\Http\Controllers;

use App\Http\Resources\ReservationReseource;
use App\Models\Reservation;
use Illuminate\Http\Request;
use Illuminate\Http\Response;

class UserReservationsController extends Controller {

    public function index(Request $request)
    {
        abort_unless(
            auth()->user()->tokenCan('reservation.show'),
            Response::HTTP_FORBIDDEN
        );


        $reservations = Reservation::query()
            ->where('user_id', auth()->id())
            ->when($request->office_id,
                fn($builder) => $builder->where('office_id', $request->office_id)
            )->when($request->status,
                fn($builder) => $builder->where('status', $request->status)
            )->when($request->from_date && $request->to_date,
                function ($builder) use ($request) {
                    $builder->whereBetween('start_date', [$request->from_date, $request->to_date])
                        ->orWhereBetween('end_date', [$request->from_date, $request->to_date]);
                }
            )
            ->with(['office', 'office.featuredImage'])
            ->paginate(20);

        return ReservationReseource::collection($reservations);
    }
}
