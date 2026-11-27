<div class="field">
    <label for="{{ $providerInputId }}">Bank / e-wallet provider *</label>
    <select class="form-control" name="provider" id="{{ $attributes->get('id','fee-provider') }}" required>
        <option value="">Select receiving institution</option>
        @foreach(['wallet'=>'E-wallets','bank'=>'Banks and digital banks'] as $type=>$groupName)
        <optgroup label="{{ $groupName }}">
            @foreach($providers->where('type',$type) as $option)
            <option value="{{ $option['name'] }}" @selected((string)$selected===(string)$option['name'])>{{ $option['name'] }}</option>
            @endforeach
        </optgroup>
        @endforeach
        @if($selected && !$providers->contains(fn($p)=>$p['name']===$selected))
        <option value="{{ $selected }}" selected>{{ $selected }} (existing institution)</option>
        @endif
    </select>
    <small class="hint">Choose the bank or wallet. The system determines the transfer type automatically.</small>
</div>
