<?php

namespace App\Http\Controllers;

use App\Http\Resources\OfficeResource;
use App\Models\Office;
use App\Models\Reservation;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Illuminate\Support\Arr;
use Illuminate\Validation\Rule;

class OfficeController extends Controller {

    public function index(Request $request): AnonymousResourceCollection
    {
        $offices = Office::query()
            ->where('approval_status', Office::APPROVAL_APPROVED)
            ->where('hidden', false)
            ->when($request->user_id, fn($builder) => $builder->where('user_id', $request->user_id))
            ->when($request->visitor_id,
                fn($builder) => $builder->whereRelation('reservations', 'user_id', '=', $request->visitor_id)
            )
            ->when(
                $request->lat && $request->lng,
                fn($builder) => $builder->nearestTo($request->lat, $request->lng),
                fn($builder) => $builder->orderBy('id', 'ASC'),
            )
            ->with(['images', 'tags', 'user'])
            ->withCount(['reservations' => fn($builder) => $builder->where('status', Reservation::STATUS_ACTIVE)])
            ->paginate(20);

        return OfficeResource::collection($offices);
    }

    public function create(Request $request)
    {
        $attributes = $request->validate([
            'title'            => ['required', 'string'],
            'description'      => ['required', 'string'],
            'lat'              => ['required', 'numeric'],
            'lng'              => ['required', 'numeric'],
            'address_line1'    => ['required', 'string'],
            'hidden'           => ['boolean'],
            'price_per_day'    => ['required', 'integer', 'min:100'],
            'monthly_discount' => ['required', 'min:0', 'max:90'],

            'tags'   => ['array'],
            'tags.*' => ['integer', Rule::exists('tags', 'id')]
        ]);

        $attributes['approval_status'] = Office::APPROVAL_PENDING;

        $office = auth()->user()->offices()->create(
            Arr::except($attributes, ['tags'])
        );

        $office->tags()->sync($attributes['tags']);

        return OfficeResource::make($office);
    }

    public function show(Office $office)
    {
        $office->loadCount(['reservations' => fn($builder) => $builder->where('status', Reservation::STATUS_ACTIVE)])
            ->load(['images', 'tags', 'user']);

        return OfficeResource::make($office);
    }
}
