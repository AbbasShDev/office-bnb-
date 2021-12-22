<?php

namespace App\Http\Controllers;

use App\Http\Resources\ReservationReseource;
use App\Models\Reservation;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Validation\Rule;

class HostReservationsController extends Controller {

    public function index(Request $request)
    {
        abort_unless(
            auth()->user()->tokenCan('reservation.show'),
            Response::HTTP_FORBIDDEN
        );

        $request->validate([
            'office_id' => ['integer'],
            'user_id'   => ['integer'],
            'status'    => [Rule::in([Reservation::STATUS_ACTIVE, Reservation::STATUS_CANCELLED])],
            'from_date' => ['date', 'required_with:to_date', 'before:to_date'],
            'to_date'   => ['date', 'required_with:from_date', 'after:from_date'],
        ]);

        $reservations = Reservation::query()
            ->whereRelation('office', 'user_id', '=', auth()->id())
            ->when($request->office_id,
                fn($builder) => $builder->where('office_id', $request->office_id)
            )->when($request->user_id,
                fn($builder) => $builder->where('user_id', $request->user_id)
            )->when($request->status,
                fn($builder) => $builder->where('status', $request->status)
            )->when($request->from_date && $request->to_date,
                fn($builder) => $builder->betweenDates($request->from_date, $request->to_date)
            )
            ->with(['office', 'office.featuredImage'])
            ->paginate(20);

        return ReservationReseource::collection($reservations);
    }
}
