<?php

namespace App\Services;

use App\Models\Admission;
use App\Models\AdmissionDocument;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;

class AdmissionDocumentService
{
    private const TYPE_MAP = [
        'photo' => 'photo',
        'id_proof' => 'id_proof',
        'address_proof' => 'address_proof',
    ];

    public function store(Admission $admission, array $files): Admission
    {
        foreach (self::TYPE_MAP as $key => $type) {
            $file = $files[$key] ?? null;
            if (! $file instanceof UploadedFile) {
                continue;
            }

            $path = $file->store("admissions/{$admission->id}", 'public');

            AdmissionDocument::updateOrCreate(
                ['admission_id' => $admission->id, 'type' => $type],
                [
                    'file_path' => $path,
                    'file_name' => $file->getClientOriginalName(),
                    'mime_type' => $file->getMimeType(),
                    'file_size' => $file->getSize(),
                ],
            );
        }

        $hasAll = AdmissionDocument::where('admission_id', $admission->id)
            ->whereIn('type', array_values(self::TYPE_MAP))
            ->count() >= 3;

        if ($hasAll) {
            $admission->update(['documents_uploaded' => true]);
        }

        return $admission->fresh(['branch', 'plan', 'documents']);
    }

    public function deleteForAdmission(Admission $admission): int
    {
        $admission->loadMissing('documents');
        $deleted = 0;

        foreach ($admission->documents as $document) {
            if ($document->file_path) {
                Storage::disk('public')->delete($document->file_path);
            }
            $document->delete();
            $deleted++;
        }

        Storage::disk('public')->deleteDirectory("admissions/{$admission->id}");

        return $deleted;
    }
}
