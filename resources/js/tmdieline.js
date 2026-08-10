/* =============================================================================
 * TMDieline — renderer dieline dari DSL prod_type.dieline_spec
 * Dipindah verbatim dari docs/mockups/inquiry_new.html (blok TMDieline, Att.1).
 * Tidak ada geometri hardcode.
 * ============================================================================= */
(function (root) {
'use strict';
var STROKE = { CUT:'#ff3b3b', CREASE:'#00e676', BLEED:'#888', HOLE:'#4da3ff' };
var DASH   = { BLEED:'5 3' };
var ALIAS = {
  A:'product_length',  B:'product_width',   C:'product_height',
  D:'diameter',        LH:'lid_height',     G:'glue_flap',
  TT:'top_tuck_flap',  DF:'dust_flap',      BT:'bottom_tuck_flap',
  P:'overflow_margin'
};
var FN = Object.assign(Object.create(null), {
  max: Math.max, min: Math.min, abs: Math.abs,
  sqrt: Math.sqrt, round: Math.round, floor: Math.floor, ceil: Math.ceil
});
var PREC = { '+':1, '-':1, '*':2, '/':2, 'u-':3 };
var tokenize = function(s){
  var t=[],i=0,re=/\s*([A-Za-z_][A-Za-z_0-9]*|\d+(?:\.\d+)?|[-+*/(),])/g,m;
  re.lastIndex=0;
  while((m=re.exec(s))!==null){
    if(m.index!==i)throw new Error('token tak dikenal: '+s.slice(i));
    t.push(m[1]);i=re.lastIndex;
  }
  if(i!==s.length&&s.slice(i).trim()!=='')throw new Error('sisa tak terparse: '+s.slice(i));
  return t;
}
var toRPN = function(tokens){
  var out=[],ops=[],prev=null,argc=[];
  for(var i=0;i<tokens.length;i++){
    var tk=tokens[ i];
    if(/^\d/.test(tk)){out.push({n:parseFloat(tk)});}
    else if(/^[A-Za-z_]/.test(tk)){
      if(tokens[i+1]==='('){ops.push({f:tk});argc.push(1);}
      else out.push({v:tk});
    }
    else if(tk===','){
      while(ops.length&&ops[ops.length-1].o!=='(')out.push(ops.pop());
      if(argc.length)argc[argc.length-1]++;
    }
    else if(tk==='('){ops.push({o:'('});}
    else if(tk===')'){
      while(ops.length&&ops[ops.length-1].o!=='(')out.push(ops.pop());
      ops.pop();
      if(ops.length&&ops[ops.length-1].f){var fo=ops.pop();fo.a=argc.pop();out.push(fo);}
    }
    else{
      var op=tk;
      if(op==='-'&&(prev===null||prev==='('||prev===','||/^[-+*/]$/.test(prev)))op='u-';
      while(ops.length){
        var top=ops[ops.length-1];
        if(top.o&&top.o!=='('&&PREC[top.o]>=PREC[ op])out.push(ops.pop());
        else break;
      }
      ops.push({o:op});
    }
    prev=tk;
  }
  while(ops.length)out.push(ops.pop());
  return out;
}
var _cache=Object.create(null);
var evalExpr = function(expr,scope){
  if(typeof expr==='number')return expr;
  if(expr===null||expr===undefined||expr==='')return 0;
  var key=String(expr);
  var rpn=_cache[ key]||(_cache[ key]=toRPN(tokenize(key)));
  var st=[];
  for(var i=0;i<rpn.length;i++){
    var t=rpn[ i];
    if('n' in t)st.push(t.n);
    else if('v' in t){
      if(t.v==='PI')st.push(Math.PI);
      else{var val=Object.prototype.hasOwnProperty.call(scope,t.v)?scope[t.v]:0;
        st.push(typeof val==='number'&&isFinite(val)?val:0);}
    }
    else if(t.f){
      var fn=FN[t.f];
      if(typeof fn!=='function')throw new Error('fungsi tak dikenal: '+t.f);
      var args=st.splice(Math.max(0,st.length-(t.a||1)));
      st.push(fn.apply(null,args));
    }
    else if(t.o==='u-')st.push(-st.pop());
    else{var b=st.pop(),a=st.pop();
      st.push(t.o==='+'?a+b:t.o==='-'?a-b:t.o==='*'?a*b:(b===0?0:a/b));}
  }
  var r=st.pop();
  return(typeof r==='number'&&isFinite(r))?r:0;
}
var buildScope = function(spec,dims){
  var s=Object.create(null),k;
  for(k in dims)if(dims.hasOwnProperty(k)){var n=parseFloat(dims[ k]);s[ k]=isNaN(n)?0:n;}
  for(k in ALIAS)s[ k]=s[ALIAS[ k]]||0;
  if(spec&&spec.vars){for(k in spec.vars)if(spec.vars.hasOwnProperty(k)){
    try{s[ k]=evalExpr(spec.vars[ k],s);}catch(e){s[ k]=0;}}}
  return s;
}
var attr = function(k){var c=STROKE[ k]||STROKE.CUT,d=DASH[ k];
  return 'fill="none" stroke="'+c+'" stroke-width="1"'+(d?' stroke-dasharray="'+d+'"':'');}
var nf = function(v){return(Math.round(v*100)/100);}
var draw_line = function(e,S){return '<line x1="'+nf(evalExpr(e.x1,S))+'" y1="'+nf(evalExpr(e.y1,S))+'" x2="'+nf(evalExpr(e.x2,S))+'" y2="'+nf(evalExpr(e.y2,S))+'" '+attr(e.k)+'/>';};
var draw_rect = function(e,S){return '<rect x="'+nf(evalExpr(e.x,S))+'" y="'+nf(evalExpr(e.y,S))+'" width="'+nf(evalExpr(e.w,S))+'" height="'+nf(evalExpr(e.h,S))+'" rx="1" '+attr(e.k)+'/>';};
var draw_circle = function(e,S){return '<circle cx="'+nf(evalExpr(e.cx,S))+'" cy="'+nf(evalExpr(e.cy,S))+'" r="'+nf(evalExpr(e.r,S))+'" '+attr(e.k)+'/>';};
var draw_tuck = function(e,S){
    var a=evalExpr(e.a,S),b=evalExpr(e.b,S),y=evalExpr(e.y,S),h=evalExpr(e.h,S);
    if(h<=0||b<=a)return '';
    var up=(e.dir!=='down'),yy=up?y-h:y+h,r=Math.min(8,(b-a)/6);
    var s=up?-1:1,d='M'+nf(a)+','+nf(y)+' L'+nf(a)+','+nf(yy-s*r)+' Q'+nf(a)+','+nf(yy)+' '+nf(a+r)+','+nf(yy)+' L'+nf(b-r)+','+nf(yy)+' Q'+nf(b)+','+nf(yy)+' '+nf(b)+','+nf(yy-s*r)+' L'+nf(b)+','+nf(y);
    return '<path d="'+d+'" '+attr(e.k)+'/>';};
var draw_dust = function(e,S){
    var a=evalExpr(e.a,S),b=evalExpr(e.b,S),y=evalExpr(e.y,S),h=evalExpr(e.h,S);
    if(h<=0||b<=a)return '';
    var up=(e.dir!=='down'),yy=up?y-h:y+h,i=Math.min(h,(b-a)/3);
    var d='M'+nf(a)+','+nf(y)+' L'+nf(a+i)+','+nf(yy)+' L'+nf(b-i)+','+nf(yy)+' L'+nf(b)+','+nf(y);
    return '<path d="'+d+'" '+attr(e.k)+'/>';};
var draw_glue = function(e,S){
    var x0=evalExpr(e.x0,S),x1=evalExpr(e.x1,S),yT=evalExpr(e.yT,S),yB=evalExpr(e.yB,S),g=evalExpr(e.g,S);
    if(x1<=x0)return '';
    var d='M'+nf(x1)+','+nf(yT)+' L'+nf(x0)+','+nf(yT+g)+' L'+nf(x0)+','+nf(yB-g)+' L'+nf(x1)+','+nf(yB);
    return '<path d="'+d+'" '+attr(e.k)+'/>';};
var draw_bleed = function(e,S){
    var p=evalExpr(e.p,S);if(p<=0)return '';
    var W=S.__W,H=S.__H;
    return '<rect x="'+nf(-p)+'" y="'+nf(-p)+'" width="'+nf(W+2*p)+'" height="'+nf(H+2*p)+'" '+attr(e.k||'BLEED')+'/>';};
var draw_foldlines = function(e,S){
    var n=Math.round(evalExpr(e.n,S)),w=evalExpr(e.w,S),h=evalExpr(e.h,S),o='';
    if(n<1||w<=0)return '';
    for(var i=1;i<=n;i++){var x=w*i/(n+1);
      o+='<line x1="'+nf(x)+'" y1="0" x2="'+nf(x)+'" y2="'+nf(h)+'" '+attr(e.k||'CREASE')+' stroke-dasharray="5 3"/>';}
    return o;};
var draw_holes = function(e,S){
    var n=Math.round(evalExpr(e.n,S)),w=evalExpr(e.w,S),y=evalExpr(e.y,S),r=evalExpr(e.r,S),o='';
    if(n<1||w<=0)return '';
    for(var i=0;i<n;i++){o+='<circle cx="'+nf(w*(i+0.5)/n)+'" cy="'+nf(y)+'" r="'+nf(r)+'" '+attr(e.k||'HOLE')+'/>';}
    return o;};
var draw_text = function(e,S){return '<text x="'+nf(evalExpr(e.x,S))+'" y="'+nf(evalExpr(e.y,S))+'" fill="#9aa" font-size="11" text-anchor="middle">'+esc(e.s)+'</text>';};
var DRAW=Object.assign(Object.create(null),{
  line:draw_line, rect:draw_rect, circle:draw_circle, tuck:draw_tuck, dust:draw_dust, glue:draw_glue, bleed:draw_bleed, foldlines:draw_foldlines, holes:draw_holes, text:draw_text
});
var esc = function(s){return String(s==null?'':s).replace(/[&<>"]/g,function(c){return{'&':'&amp;','<':'&lt;','>':'&gt;','"':'&quot;'}[ c];});}
var placeholder = function(msg){return '<div style="color:#889;font-size:12px;height:100%;display:flex;align-items:center;justify-content:center;text-align:center;padding:20px">'+msg+'</div>';}
var flat = function(spec,dims){
  if(!spec||!spec.flat)return{w:0,h:0,area:0};
  var S=buildScope(spec,dims),w=0,h=0;
  try{w=evalExpr(spec.flat.w,S);}catch(e1){w=0;}
  try{h=evalExpr(spec.flat.h,S);}catch(e2){h=0;}
  w=Math.round(w*100)/100;h=Math.round(h*100)/100;
  return{w:w,h:h,area:Math.round(w*h*100)/100};
}
var svg = function(spec,dims){
  if(!spec||!spec.elements)return null;
  var S=buildScope(spec,dims),W=0,H=0;
  try{W=evalExpr(spec.canvas?spec.canvas.w:'W',S);}catch(e1){W=0;}
  try{H=evalExpr(spec.canvas?spec.canvas.h:'H',S);}catch(e2){H=0;}
  if(!(W>0)||!(H>0))return null;
  S.__W=W;S.__H=H;
  var body='';
  for(var i=0;i<spec.elements.length;i++){
    var e=spec.elements[ i],fn=DRAW[e.t];
    if(typeof fn!=='function')continue;
    try{body+=fn(e,S);}catch(err){}
  }
  var M=Math.max(24,(W+H)/16);
  return '<svg width="100%" height="100%" viewBox="'+nf(-M)+' '+nf(-M)+' '+nf(W+2*M)+' '+nf(H+2*M)+'" xmlns="http://www.w3.org/2000/svg">'+body+'</svg>';
}
var render = function(el,spec,dims){
  if(!el)return;
  if(!spec){el.innerHTML=placeholder('Tipe ini belum punya<br>spesifikasi dieline');return;}
  var s=svg(spec,dims);
  el.innerHTML=s||placeholder('Pilih ukuran / isi dimensi<br>untuk melihat dieline');
}
root.TMDieline={render:render,svg:svg,flat:flat,eval:evalExpr,scope:buildScope,STROKE:STROKE};
})(typeof window!=='undefined'?window:this);
