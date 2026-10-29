<button {{ $attributes->merge(['type' => 'submit', 'class' => 'inline-flex items-center px-4 py-2 bg-[#9c8d45] border border-transparent rounded-md font-semibold text-xs text-stone-950 uppercase tracking-widest hover:bg-[#857739] focus:bg-[#857739] active:bg-[#706430] focus:outline-none focus:ring-2 focus:ring-[#9c8d45] focus:ring-offset-2 transition ease-in-out duration-150 shadow-sm']) }}>
    {{ $slot }}
</button>
