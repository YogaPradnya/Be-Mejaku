<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class CreatePaymentRequest extends FormRequest
{
    /**
     * Determine if the user is authorized to make this request.
     */
    public function authorize(): bool
    {
        return true;
    }

    /**
     * Get the validation rules that apply to the request.
     */
    public function rules(): array
    {
        return [
            'method' => 'required|string|in:qris,bank_transfer,e_wallet,midtrans,cashier',
            'channel' => 'nullable|string|in:qris,va',
            'bank' => 'nullable|string|in:bca,bri,bni,permata',
        ];
    }

    /**
     * Get custom messages for validator errors.
     */
    public function messages(): array
    {
        return [
            'method.required' => 'Metode pembayaran wajib dipilih.',
            'method.in' => 'Metode pembayaran tidak valid. Pilih: qris, bank_transfer, e_wallet, midtrans, atau cashier.',
        ];
    }
}
