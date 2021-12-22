<?php

namespace App\Http\Controllers;

use App\Http\Resources\ReservationReseource;
use App\Models\Office;
use App\Models\Reservation;
use Carbon\Carbon;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\Cache;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;

class UserReservationsController extends Controller {

    public function index(Request $request)
    {
        abort_unless(
            auth()->user()->tokenCan('reservation.show'),
            Response::HTTP_FORBIDDEN
        );

        $request->validate([
            'office_id' => ['integer'],
            'status'    => [Rule::in([Reservation::STATUS_ACTIVE, Reservation::STATUS_CANCELLED])],
            'from_date' => ['date', 'required_with:to_date', 'before:to_date'],
            'to_date'   => ['date', 'required_with:from_date', 'after:from_date'],
        ]);

        $reservations = Reservation::query()
            ->where('user_id', auth()->id())
            ->when($request->office_id,
                fn($builder) => $builder->where('office_id', $request->office_id)
            )->when($request->status,
                fn($builder) => $builder->where('status', $request->status)
            )->when($request->from_date && $request->to_date,
                fn($builder) => $builder->betweenDates($request->from_date, $request->to_date)
            )
            ->with(['office', 'office.featuredImage'])
            ->paginate(20);

        return ReservationReseource::collection($reservations);
    }

    public function create(Request $request)
    {
        abort_unless(
            auth()->user()->tokenCan('reservation.create'),
            Response::HTTP_FORBIDDEN
        );

        $attributes = $request->validate([
            'office_id'  => ['required', 'integer'],
            'start_date' => ['required', 'date', 'after:' . now()->addDay()->toDateString()],
            'end_date'   => ['required', 'date', 'after:start_date'],
        ]);

        try {
            $office = Office::findOrFail($attributes->office_id);
        } catch (ModelNotFoundException $e) {
            throw ValidationException::withMessages(['office_id' => 'Invalid office_id']);
        }

        throw_if(
            $office->user_id == auth()->id(),
            ValidationException::withMessages(['office_id' => 'You can not make reservation for your office'])
        );

        $reservation = Cache::lock('reservation_office_' . $office->id, 10)->block(3, function () use ($attributes, $office) {

            throw_if(
                $office->reservaions()->activeBetween($attributes->start_date, $attributes->end_date)->exists(),
                ValidationException::withMessages(['office_id' => 'You can not make reservation during this period of time'])
            );

            $numberOfDays = Carbon::parse($attributes->end_date)
                ->endOfDay()
                ->diffInDays(
                    Carbon::parse($attributes->start_date)->startOfDay()
                );


            $price = $numberOfDays * $office->price_per_day;

            if ($numberOfDays >= 28 && $office->monthly_discount) {
                $price = $price - ($office->monthly_discount / 100);
            }

            return Reservation::create([
                'user_id'    => auth()->id(),
                'office_id'  => $office->id,
                'start_date' => $attributes->start_date,
                'end_date'   => $attributes->end_date,
                'status'     => Reservation::STATUS_ACTIVE,
                'price'      => $price,
            ]);

        });

        return ReservationReseource::make($reservation->load('office'));
    }
}
