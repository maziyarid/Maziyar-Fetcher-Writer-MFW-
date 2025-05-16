<?php
/**
 * Select Field Template
 * 
 * @package MFW
 * @subpackage Views
 * @since 1.0.0
 */

// If this file is called directly, abort.
if (!defined('ABSPATH')) {
    exit;
}
?>
<select
    id="{{ $field['id'] }}"
    name="{{ $field['name'] ?? $field['id'] }}{{ !empty($field['multiple']) ? '[]' : '' }}"
    class="mfw-select-field {{ $field['class'] ?? '' }}"
    @if(!empty($field['multiple']))
        multiple
    @endif
    @if(!empty($field['size']))
        size="{{ $field['size'] }}"
    @endif
    @if(!empty($field['required']))
        required
    @endif
    @if(!empty($field['disabled']))
        disabled
    @endif
    {!! $field['attributes'] ?? '' !!}
>
    @if(!empty($field['placeholder']))
        <option value="">{{ $field['placeholder'] }}</option>
    @endif

    @if(!empty($field['optgroups']))
        @foreach($field['optgroups'] as $group)
            <optgroup label="{{ $group['label'] }}">
                @foreach($group['options'] as $option)
                    <option 
                        value="{{ $option['value'] }}"
                        @if((!empty($field['multiple']) && in_array($option['value'], (array)$value)) || 
                            (!empty($value) && $option['value'] == $value))
                            selected
                        @endif
                        @if(!empty($option['disabled']))
                            disabled
                        @endif
                        {!! $option['attributes'] ?? '' !!}
                    >
                        {{ $option['label'] }}
                    </option>
                @endforeach
            </optgroup>
        @endforeach
    @else
        @foreach($field['options'] as $option)
            <option 
                value="{{ $option['value'] }}"
                @if((!empty($field['multiple']) && in_array($option['value'], (array)$value)) || 
                    (!empty($value) && $option['value'] == $value))
                    selected
                @endif
                @if(!empty($option['disabled']))
                    disabled
                @endif
                {!! $option['attributes'] ?? '' !!}
            >
                {{ $option['label'] }}
            </option>
        @endforeach
    @endif
</select>