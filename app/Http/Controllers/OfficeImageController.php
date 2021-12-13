<?php

namespace App\Http\Controllers;

use App\Http\Resources\ImageResource;
use App\Models\Image;
use App\Models\Office;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\ValidationException;

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

        $path = $request->image->storePublicly('/');

        $image = $office->images()->create(['path' => $path]);

        return ImageResource::make($image);
    }

    public function destroy(Office $office, Image $image)
    {
        abort_unless(
            auth()->user()->tokenCan('office.update'),
            Response::HTTP_FORBIDDEN
        );

        $this->authorize('update', $office);

        throw_if($image->resource_type != 'office' || $image->resource_id != $office->id,
            ValidationException::withMessages(['image' => 'You can not delete this image'])
        );

        throw_if($office->images()->count() == 1,
            ValidationException::withMessages(['image' => 'You can not delete the only image for office'])
        );

        throw_if($office->featured_image_id == $image->id,
            ValidationException::withMessages(['image' => 'You can not delete the featured image'])
        );

        Storage::delete($image->path);

        $image->delete();

        return response()->json(['Deleted successfully']);
    }
}
