@extends('layouts.app')

@section('title', 'Template Builder')
@section('heading', 'Template Builder')
@section('styles')<link href="{{ asset('css/oms-builder.css') }}" rel="stylesheet">
<style>
    .bwrap{display:grid;grid-template-columns:230px minmax(0,1fr) 360px;gap:16px;padding:20px 24px;font-family:"Plus Jakarta Sans",system-ui,sans-serif;}
    .bcol{background:#fff;border:1px solid #E6E9EE;border-radius:12px;padding:14px;}
    .bh{font-size:11px;font-weight:800;letter-spacing:.05em;text-transform:uppercase;color:#8B93A1;margin:0 0 10px;}
    .pitem,.cblock{display:flex;align-items:center;gap:8px;border:1px solid #D8DCE3;border-radius:8px;padding:8px 10px;margin-bottom:7px;background:#FAFBFC;font-size:12.5px;cursor:grab;}
    .pitem.tok{background:#EBF2FE;color:#1A4BA6;border-color:#D6E4FD;font-family:"IBM Plex Mono",monospace;}
    .cblock{background:#fff;cursor:default;justify-content:space-between;}
    .cblock .ce{flex:1;outline:none;}
    .cblock .rm{border:none;background:none;color:#C0392B;cursor:pointer;font-weight:700;}
    .toolbar{display:flex;gap:10px;align-items:center;margin-bottom:12px;padding:0 24px;}
    .btn{font:inherit;font-size:13px;font-weight:650;border-radius:8px;border:1px solid #D8DCE3;background:#fff;padding:8px 13px;cursor:pointer;}
    .btn.primary{background:#2C6FE0;color:#fff;border-color:#2C6FE0;}
    .pv{white-space:pre-wrap;font-size:13px;line-height:1.5;background:#E7FFDB;border-radius:10px;padding:12px;color:#111B16;}
    .warn{background:#FBEED7;border:1px solid #F0D9A8;color:#A66400;border-radius:8px;padding:9px 11px;font-size:12px;margin:0 24px 12px;display:none;}
    .warn.on{display:block;}
    .meta{font-size:12px;color:#555E6C;margin-left:auto;}
</style>@endsection

@section('content')
<div class="toolbar">
    <b>Template Builder · {{ $template->name }} <span class="meta">scope: {{ $template->scope }}</span></b>
    <button type="button" class="btn" onclick="saveDraft()">Simpan draft</button>
    <button type="button" class="btn primary" onclick="publishLatest()">Publish</button>
</div>
<div class="warn" id="warn"></div>

<div class="bwrap">
    <div class="bcol">
        <p class="bh">Blok</p>
        <div id="structPalette">
            @foreach (['greeting' => 'Sapaan', 'section' => 'Bagian', 'text' => 'Catatan', 'adjustment' => 'Penyesuaian Revenue'] as $t => $label)
                <div class="pitem" draggable="true" data-add="{{ $t }}">{{ $label }}</div>
            @endforeach
        </div>
        <p class="bh" style="margin-top:14px">Token</p>
        <div id="tokenPalette">
            @foreach ($tokens as $tok)
                <div class="pitem tok" draggable="true" data-token="{{ $tok }}">&#123;&#123;{{ $tok }}&#125;&#125;</div>
            @endforeach
        </div>
    </div>

    <div class="bcol">
        <p class="bh">Kanvas (drag untuk urutkan)</p>
        <div id="canvas"></div>
    </div>

    <div class="bcol">
        <p class="bh">Pratinjau (data contoh)</p>
        <div class="pv" id="preview"></div>
    </div>
</div>
@endsection

@section('scripts')
<script>
    window.BUILDER = {
        sample: @json($sample),
        draftUrl: @json(route('admin.templates.draft', $template)),
        blocks: @json($template->layout_json ?? []),
    };
</script>
@verbatim
<script>
    const SAMPLE = window.BUILDER.sample;
    const RUPIAH = ['total_sales','realized','piutang','avg_transaction','avg_customer_spending'];
    const DRAFT_URL = window.BUILDER.draftUrl;
    const CSRF = document.querySelector('meta[name=csrf-token]').content;
    let blocks = window.BUILDER.blocks || [];

    function rp(v){ return 'Rp'+Number(v).toLocaleString('id-ID'); }
    function fmt(token, val){
        if(val===undefined||val===null||val==='') return '';
        if(token==='tanggal') return '12 Juni 2026';
        return RUPIAH.includes(token) ? rp(val) : String(val);
    }
    function interp(text){ return (text||'').replace(/\{\{\s*([a-z_]+)\s*\}\}/g,(m,t)=>fmt(t,SAMPLE[t])); }
    function isZero(v){ return v===0||v==='0'||v===undefined||v===null||v===''; }

    function renderCanvas(){
        const c = document.getElementById('canvas');
        c.innerHTML = blocks.map((b,i)=>{
            const editable = (b.type==='greeting'||b.type==='section'||b.type==='text')
                ? `<span class="ce" contenteditable="true" data-i="${i}" data-f="text">${b.text||''}</span>`
                : b.type==='kv'
                ? `<span class="ce" contenteditable="true" data-i="${i}" data-f="label">${b.label||b.token}</span> <code>{{${b.token}}}</code>`
                : `<span>Blok Penyesuaian Revenue</span>`;
            return `<div class="cblock" draggable="true" data-i="${i}">${editable}<button type="button" class="rm" onclick="del(${i})">×</button></div>`;
        }).join('') || '<div style="color:#8B93A1;font-size:12px">Tarik blok/token ke sini.</div>';
        bindEdit(); renderPreview();
    }
    function bindEdit(){
        document.querySelectorAll('.ce').forEach(el=>el.addEventListener('input',()=>{
            const i=+el.dataset.i, f=el.dataset.f; blocks[i][f]=el.textContent; renderPreview();
        }));
        document.querySelectorAll('.cblock').forEach(el=>{
            el.addEventListener('dragstart',e=>{ e.dataTransfer.setData('reorder',el.dataset.i); });
            el.addEventListener('dragover',e=>e.preventDefault());
            el.addEventListener('drop',e=>{ e.preventDefault(); const from=e.dataTransfer.getData('reorder'); if(from!=='') move(+from,+el.dataset.i); });
        });
    }
    window.del=(i)=>{ blocks.splice(i,1); renderCanvas(); };
    function move(from,to){ const [b]=blocks.splice(from,1); blocks.splice(to,0,b); renderCanvas(); }

    function renderPreview(){
        const lines=[];
        for(const b of blocks){
            if(['greeting','section','text'].includes(b.type)) lines.push(interp(b.text));
            else if(b.type==='kv'){ const v=SAMPLE[b.token]; if(!isZero(v)) lines.push((b.label||b.token)+': '+fmt(b.token,v)); }
            else if(b.type==='adjustment'){ if(SAMPLE.penyesuaian_revenue) lines.push(SAMPLE.penyesuaian_revenue); }
        }
        document.getElementById('preview').textContent = lines.join('\n');
    }

    // palette drag → append
    document.querySelectorAll('#structPalette .pitem, #tokenPalette .pitem').forEach(p=>{
        p.addEventListener('dragstart',e=>{
            e.dataTransfer.setData('add', p.dataset.add||''); e.dataTransfer.setData('token', p.dataset.token||'');
        });
    });
    const canvas=document.getElementById('canvas');
    canvas.addEventListener('dragover',e=>e.preventDefault());
    canvas.addEventListener('drop',e=>{
        const add=e.dataTransfer.getData('add'), token=e.dataTransfer.getData('token');
        if(token){ blocks.push({type:'kv',label:token,token}); renderCanvas(); }
        else if(add==='greeting') blocks.push({type:'greeting',text:'Halo {{nama_investor}}'}) & renderCanvas();
        else if(add==='section') blocks.push({type:'section',text:'BAGIAN'}) & renderCanvas();
        else if(add==='text') blocks.push({type:'text',text:'Catatan'}) & renderCanvas();
        else if(add==='adjustment') blocks.push({type:'adjustment',token:'penyesuaian_revenue'}) & renderCanvas();
    });

    async function post(url){
        const r = await fetch(url,{method:'POST',headers:{'Content-Type':'application/json','X-CSRF-TOKEN':CSRF,'Accept':'application/json'},body:JSON.stringify({layout_json:blocks})});
        return {status:r.status, body:await r.json().catch(()=>({}))};
    }
    window.saveDraft=async()=>{
        const {status,body}=await post(DRAFT_URL);
        const w=document.getElementById('warn');
        if(status>=400){ w.textContent=body.error||'Gagal simpan'; w.classList.add('on'); return; }
        w.classList.toggle('on', !body.fits_approved_template);
        if(!body.fits_approved_template) w.textContent=body.warning;
        alert('Draft v'+body.version+' tersimpan.');
    };
    window.publishLatest=()=>alert('Simpan draft lalu publish dari riwayat versi (OPS-1004).');

    renderCanvas();
</script>
@endverbatim
@endsection
