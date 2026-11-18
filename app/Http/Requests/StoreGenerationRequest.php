<?php

namespace App\Http\Requests;

use App\Enums\GenerationQuality;
use App\Enums\GenerationSize;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreGenerationRequest extends FormRequest
{
    protected function prepareForValidation(): void
    {
        $this->merge([
            'reference_images' => array_values(array_filter((array) $this->input('reference_images', []), filled(...))),
        ]);
    }

    /**
     * @return array<string, array<int, mixed>>
     */
    public function rules(): array
    {
        return [
            'prompt' => ['required', 'string', 'max:4000'],
            'size' => ['required', Rule::enum(GenerationSize::class)],
            'quality' => ['required', Rule::enum(GenerationQuality::class)],
            'reference_images' => ['array', 'max:4'],
            'reference_images.*' => ['url:http,https', 'max:2048', 'distinct'],
        ];
    }

    public function messages(): array
    {
        return [
            'reference_images.max' => __('You can add up to 4 reference images.'),
            'reference_images.*.url' => __('Each reference image must be a valid http or https URL.'),
            'reference_images.*.distinct' => __('Each reference image URL must be different.'),
        ];
    }

    /**
     * @return list<string>
     */
    public function referenceImages(): array
    {
        return $this->validated('reference_images', []);
    }
}
