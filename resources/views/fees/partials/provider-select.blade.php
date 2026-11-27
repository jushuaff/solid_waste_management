<div class="field">
    <div class="row-between" style="gap:10px;align-items:center">
        <label for="{{ $providerInputId }}">Bank / e-wallet provider *</label>
        @if(auth()->user()->role_code==='superadmin')
        <button type="button" class="fee-provider-add-link"
            onclick="const panel=document.getElementById('fee-provider-create');if(panel){panel.open=true;panel.scrollIntoView({behavior:'smooth',block:'center'});}">
            + Add provider
        </button>
        @endif
    </div>
    <select class="form-control" name="provider" id="{{ $providerInputId }}" required>
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
