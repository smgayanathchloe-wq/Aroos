{{-- "Draft with AI" button that fills a textarea. Hidden when AI is off. Expects $kind, $target (textarea id), optional $enquiryId. --}}
@if(config('marketplace.ai.enabled'))
    <button type="button" class="text-xs text-gold hover:underline" data-assist="{{ $kind }}" data-target="{{ $target }}" @isset($enquiryId) data-enquiry="{{ $enquiryId }}" @endisset>✨ Draft with AI</button>
    @once
        @push('head')
        <script>
        document.addEventListener('click', async (e) => {
            const btn = e.target.closest('[data-assist]');
            if (!btn) return;
            const field = document.getElementById(btn.dataset.target);
            if (field.value.trim() && !confirm('Replace the current text with an AI draft? You can edit it before saving.')) return;
            const label = btn.textContent; btn.disabled = true; btn.textContent = 'Writing…';
            try {
                const res = await fetch(@json(route('provider.assist')), {
                    method: 'POST',
                    headers: {'Content-Type': 'application/json', 'Accept': 'application/json', 'X-CSRF-TOKEN': @json(csrf_token())},
                    body: JSON.stringify({kind: btn.dataset.assist, enquiry_id: btn.dataset.enquiry || null, notes: field.value}),
                });
                const data = await res.json();
                if (res.ok) { field.value = data.text; field.focus(); } else { alert(data.message || 'The assistant is unavailable.'); }
            } catch { alert('The assistant is unavailable.'); }
            btn.disabled = false; btn.textContent = label;
        });
        </script>
        @endpush
    @endonce
@endif
