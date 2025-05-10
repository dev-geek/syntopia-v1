<?php

namespace App\Imports;

use App\Models\User; // Assuming you're importing user data
use Maatwebsite\Excel\Concerns\ToModel;
use Maatwebsite\Excel\Concerns\WithHeadingRow;  // Optional: if your sheet has a header row
use Illuminate\Support\Str;
use App\Mail\UserRegistrationMail;
use Illuminate\Support\Facades\Mail;


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

        // Send an email with the user's login credentials
        Mail::to($user->email)->send(new UserRegistrationMail($user, $password));

        return $user;
    }
}
