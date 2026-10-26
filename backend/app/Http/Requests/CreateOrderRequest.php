<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class CreateOrderRequest extends FormRequest
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
            'table_code' => 'required|string|exists:tables,code',
            'delivery_type' => 'sometimes|string|in:dine_in,delivery',
            'notes' => 'nullable|string|max:150',
            'promo_code' => 'nullable|string|max:50',
            'idempotency_key' => 'nullable|string|max:255',
            'items' => 'required|array|min:1',
            'items.*.menu_item_id' => 'required|integer|exists:menu_items,id',
            'items.*.portion_id' => 'nullable|integer|exists:menu_portions,id',
            'items.*.spice_level_id' => 'nullable|integer|exists:spice_levels,id',
            'items.*.quantity' => 'required|integer|min:1',
            'items.*.addon_ids' => 'nullable|array',
            'items.*.addon_ids.*' => 'integer|exists:menu_addons,id',
        ];
    }

    /**
     * Get custom messages for validator errors.
     */
    public function messages(): array
    {
        return [
            'table_code.required' => 'Kode meja wajib diisi.',
            'table_code.exists' => 'Kode meja tidak ditemukan.',
            'notes.max' => 'Catatan maksimal 150 karakter.',
            'items.required' => 'Minimal satu item harus dipesan.',
            'items.min' => 'Minimal satu item harus dipesan.',
            'items.*.menu_item_id.required' => 'ID menu wajib diisi.',
            'items.*.menu_item_id.exists' => 'Menu tidak ditemukan.',
            'items.*.quantity.min' => 'Jumlah minimal 1.',
        ];
    }
}
