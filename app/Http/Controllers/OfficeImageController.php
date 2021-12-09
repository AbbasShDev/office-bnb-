<?php

namespace App\Http\Controllers;

use App\Http\Resources\ImageResource;
use App\Models\Office;
use Illuminate\Http\Request;
use Illuminate\Http\Response;

class OfficeImageController extends Controller {

    public function store(Request $request, Office $office)
    {
        abort_unless(
            auth()->user()->tokenCan('office.update'),
            Response::HTTP_FORBIDDEN
        );

        $this->authorize('update', $office);

        $request->validate([
            'image' => ['required', 'file', 'max:5000', 'mimes:png,jpg']
        ]);

        $path = $request->image->storePublicly('/', ['disk' => 'public']);

        $image = $office->images()->create(['path' => $path]);

        return ImageResource::make($image);
    }
}