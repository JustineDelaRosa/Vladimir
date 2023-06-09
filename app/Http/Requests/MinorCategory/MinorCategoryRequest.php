<?php

namespace App\Http\Requests\MinorCategory;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class MinorCategoryRequest extends FormRequest
{
    /**
     * Determine if the user is authorized to make this request.
     *
     * @return bool
     */
    public function authorize()
    {
        return true;
    }

    /**
     * Get the validation rules that apply to the request.
     *
     * @return array
     */


    protected function prepareForValidation()
    {
        $this->merge(['minor_category_id' => $this->route()->parameter('minor_category')]);
        $this->merge(['id' => $this->route()->parameter('id')]);
    }

    public function rules()
    {
        if ($this->isMethod('post')) {
            return [
                'major_category_id' => 'required|exists:major_categories,id,deleted_at,NULL',
                //if minor category name and major category id has duplicate
                'minor_category_name' => 'required',
            ];
        }

        if ($this->isMethod('put') &&  ($this->route()->parameter('minor_category'))) {
            $id = $this->route()->parameter('minor_category');
            return [
//                'major_category_id' => 'required|exists:major_categories,id,deleted_at,NULL',
            //based on the id of the minor category, if the minor category name and major category id has duplicate
                    'minor_category_name' => ['required', Rule::unique('minor_categories')->where(function ($query) use ($id) {
                        return $query->where('id', '!=', $id);
                    })],
            ];
        }

        if ($this->isMethod('get') && ($this->route()->parameter('minor_category'))) {
            return [
                // 'minor_category_id' => 'exists:minor_categories,id,deleted_at,NULL'
            ];
        }

        if ($this->isMethod('put') && ($this->route()->parameter('id'))) {
            return [
                'status' => 'required|boolean',
                // 'id' => 'exists:minor_categories,id',
            ];
        }
    }

    function messages()
    {
        return [
            'major_category_id.required' => 'Major Category is required',
            'major_category_id.exists' => 'Major Category does not exist',
            'minor_category_name.required' => 'Minor Category Name is required',
            'minor_category_name.unique' => 'Minor Category Name already been taken',
            'minor_category_name.exists' => 'Minor Category does not exist',
            'minor_category_id.exists' => 'Minor Category does not exist',
            'status.required' => 'Status is required',
            'status.boolean' => 'Status must be boolean',
        ];
    }
}
