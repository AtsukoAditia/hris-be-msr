<?php

namespace App\Services;

use App\Models\EmergencyContact;
use App\Models\Employee;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class EmergencyContactService
{
    private const MAX_CONTACTS = 5;

    public function create(Employee $employee, array $validated): EmergencyContact
    {
        return DB::transaction(function () use ($employee, $validated): EmergencyContact {
            $contacts = $employee->emergencyContacts()
                ->reorder()
                ->lockForUpdate()
                ->get();

            if ($contacts->count() >= self::MAX_CONTACTS) {
                throw ValidationException::withMessages([
                    'emergency_contacts' => 'Maksimal lima kontak darurat untuk setiap karyawan.',
                ]);
            }

            $isPrimary = (bool) ($validated['is_primary'] ?? false) || $contacts->isEmpty();

            if ($isPrimary) {
                $employee->emergencyContacts()->update(['is_primary' => false]);
            }

            return $employee->emergencyContacts()->create([
                ...$validated,
                'is_primary' => $isPrimary,
            ]);
        });
    }

    public function update(Employee $employee, EmergencyContact $contact, array $validated): EmergencyContact
    {
        return DB::transaction(function () use ($employee, $contact, $validated): EmergencyContact {
            $employee->emergencyContacts()->reorder()->lockForUpdate()->get();
            $requestedPrimary = array_key_exists('is_primary', $validated)
                ? (bool) $validated['is_primary']
                : null;

            if ($requestedPrimary === true) {
                $employee->emergencyContacts()
                    ->whereKeyNot($contact->id)
                    ->update(['is_primary' => false]);
            }

            $contact->update($validated);

            if ($requestedPrimary === false && $contact->wasChanged('is_primary')) {
                $replacement = $employee->emergencyContacts()
                    ->reorder()
                    ->whereKeyNot($contact->id)
                    ->oldest('id')
                    ->first();

                if ($replacement) {
                    $replacement->update(['is_primary' => true]);
                } else {
                    $contact->update(['is_primary' => true]);
                }
            }

            $this->ensurePrimaryContact($employee);

            return $contact->refresh();
        });
    }

    public function delete(Employee $employee, EmergencyContact $contact): void
    {
        DB::transaction(function () use ($employee, $contact): void {
            $wasPrimary = $contact->is_primary;
            $contact->delete();

            if ($wasPrimary) {
                $replacement = $employee->emergencyContacts()
                    ->reorder()
                    ->oldest('id')
                    ->first();
                $replacement?->update(['is_primary' => true]);
            }
        });
    }

    private function ensurePrimaryContact(Employee $employee): void
    {
        if ($employee->emergencyContacts()->where('is_primary', true)->exists()) {
            return;
        }

        $employee->emergencyContacts()
            ->reorder()
            ->oldest('id')
            ->first()
            ?->update(['is_primary' => true]);
    }
}
