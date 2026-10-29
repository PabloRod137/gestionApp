@props(['disabled' => false])

<input @disabled($disabled) {{ $attributes->merge(['class' => 'border-gray-300 focus:border-[#A28C3F] focus:ring-[#A28C3F] rounded-md shadow-sm']) }}>
