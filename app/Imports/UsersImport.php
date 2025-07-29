<?php

namespace App\Imports;

use Maatwebsite\Excel\Concerns\ToModel;
use Maatwebsite\Excel\Concerns\WithHeadingRow;
use App\Models\User;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;
use Illuminate\Support\Facades\Log;
use App\Mail\UserRegistrationMail;
use Illuminate\Support\Facades\Mail;
use App\Services\MailService;


class UsersImport implements ToModel, WithHeadingRow // Implement the right interfaces
{
    /**
     * Map the data to the User model.
     *
     * @param array $row
     * @return \Illuminate\Database\Eloquent\Model|null
     */
    public function model(array $row)
    {
        // Generate a random password for each user
        $password = Str::random(8);  // You can adjust the length as needed

        // Create a new user and store the password as a hashed value
        $user = new User([
            'name' => $row['name'],   // Assuming your Excel sheet has 'name' column
            'email' => $row['email'],  // Assuming your Excel sheet has 'email' column
            'password' => bcrypt($password),
            'role' => 3,
            'status' => 1,  // Hash the password before saving
        ]);

        // Save the user to the database
        $user->save();

        // Send email with password using MailService
        $mailResult = MailService::send($user->email, new UserRegistrationMail($user, $password));

        if (!$mailResult['success']) {
            // Log the error but don't fail the import
            Log::warning('Failed to send registration email during import', [
                'user_id' => $user->id,
                'email' => $user->email,
                'error' => $mailResult['error'] ?? 'Unknown error'
            ]);
        }

        return $user;
    }
}
