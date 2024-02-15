<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;

class PaymentController extends Controller
{
    public function pay()
    {
        return view('front-end.pay.index');
    }


    public function make_payment()
   {
    $validator = Validator::make($request->all(), [
        'name' => 'required|string',
        'email' => 'required|email',
        'amount' => 'required|numeric',
        'payment_for' => 'required|string',
    ]);

    if ($validator->fails()) {
        return back()->withErrors($validator)->withInput();
    }

    $formData = [
        'name' => $request->input('name'),
        'email' => $request->input('email'),
        'amount' => $request->input('amount') * 100,
        'payment_for' => $request->input('payment_for'),
        'callback_url' => route('pay.callback')
    ];

    $pay = json_decode($this->initiate_payment($formData));

    if ($pay) {
        // if ($pay->status) {
            if ($pay && $pay->status) {
            // Do not store in the database until payment is successful
            // Add a check for successful payment
            if ($this->isPaymentSuccessful($pay->data->reference)) {
                // Store payment information in the database
                Payment::create([
                    'name' => $formData['name'],
                    'email' => $formData['email'],
                    'amount' => $formData['amount'],
                    'payment_for' => $formData['payment_for'],
                    'authorization_url' => $pay->data->authorization_url,
                    'reference' => $pay->data->reference,
                ]);

                return redirect($pay->data->authorization_url);
            } else {
                return back()->withError("Payment was not successful");
            }
        } else {
            return back()->withError($pay->message);
        }
    } else {
        return back()->withError("Something went wrong");
    }
}


    public function payment_callback()
{
    // Step 1: Verify the payment status using the payment reference
    $response = json_decode($this->verify_payment(request('reference')));

    // Step 2: Check the result of the payment verification
    if ($response) {
        // Step 3: If the payment verification is successful
        if ($response->status) {
            // Extract payment data from the response
            $data = $response->data;

            // Step 4: Return a view with the payment data
            return view('front-end.pay.callback_page')->with(compact(['data']));
        } else {
            // Step 5: If there is an error in payment verification, redirect back with an error message
            return back()->withError($response->message);
        }
    } else {
        // Step 6: If something goes wrong in the process, redirect back with a generic error message
        return back()->withError("Something went wrong");
    }
}

    public function initiate_payment($formData)
{
    // Paystack API endpoint for initializing a transaction
    $url = "https://api.paystack.co/transaction/initialize";

    // Convert the form data into a URL-encoded string
    $fields_string = http_build_query($formData);

    // Initialize cURL session
    $ch = curl_init();

    // Set cURL options
    curl_setopt($ch, CURLOPT_URL, $url); // Set the URL
    curl_setopt($ch, CURLOPT_POST, true); // Set HTTP POST method
    curl_setopt($ch, CURLOPT_POSTFIELDS, $fields_string); // Set the POST fields
    curl_setopt($ch, CURLOPT_HTTPHEADER, array(
        "Authorization: Bearer " . env('PAYSTACK_SECRET_KEY'), // Set the Paystack secret key in the Authorization header
        "Cache-Control: no-cache", // Set Cache-Control header
    ));
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true); // Return the transfer as a string

    // Execute cURL session and store the result
    $result = curl_exec($ch);

    // Close cURL session
    curl_close($ch);

    // Return the result obtained from the Paystack API
    return $result;
}

public function verify_payment($reference)
{
    // Step 1: Initialize cURL session
    $curl = curl_init();

    // Step 2: Set cURL options
    curl_setopt_array($curl, array(
        CURLOPT_URL => "https://api.paystack.co/transaction/verify/$reference", // Paystack API endpoint for verifying a transaction
        CURLOPT_RETURNTRANSFER => true, // Return the transfer as a string
        CURLOPT_ENCODING => "", // Enable compression
        CURLOPT_MAXREDIRS => 10, // Follow up to 10 redirects
        CURLOPT_TIMEOUT => 30, // Timeout in seconds
        CURLOPT_HTTP_VERSION => CURL_HTTP_VERSION_1_1, // Use HTTP/1.1
        CURLOPT_CUSTOMREQUEST => "GET", // Set HTTP GET method
        CURLOPT_HTTPHEADER => array(
            "Authorization: Bearer " . env('PAYSTACK_SECRET_KEY'), // Set the Paystack secret key in the Authorization header
            "Cache-Control: no-cache", // Set Cache-Control header
        ),
    ));

    // Step 3: Execute cURL session and store the response
    $response = curl_exec($curl);

    // Step 4: Close cURL session
    curl_close($curl);

    // Step 5: Return the response obtained from the Paystack API
    return $response;
}
}
