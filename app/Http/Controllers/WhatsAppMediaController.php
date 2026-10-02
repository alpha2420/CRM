<?php

namespace App\Http\Controllers;

use App\Media\MediaLibrary;
use App\Models\WhatsAppMessage;
use Illuminate\Support\Facades\Gate;
use Symfony\Component\HttpFoundation\StreamedResponse;

/**
 * A photo, voice note, video or document a lead sent, for team members
 * who may see the lead.
 */
class WhatsAppMediaController extends Controller
{
    public function __invoke(WhatsAppMessage $message, MediaLibrary $library): StreamedResponse
    {
        Gate::authorize('view', $message->lead);
        abort_if($message->media_path === null, 404);

        return $library->response($message);
    }
}
