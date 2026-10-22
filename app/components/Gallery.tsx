import {useEffect,useRef,useState} from 'react';
import {photos} from '../lib/content';

export default function Gallery({reduced,onOpen,captions}:{reduced:boolean;onOpen:(index:number,event:React.MouseEvent<HTMLButtonElement>)=>void;captions?:string[]}){
 const section=useRef<HTMLElement>(null),rail=useRef<HTMLDivElement>(null);
 const drag=useRef<{x:number;y:number;scroll:number}|null>(null),suppressClick=useRef(false);
 const [index,setIndex]=useState(0),[progress,setProgress]=useState(0),[cat,setCat]=useState(false);
 const top=()=>{const node=section.current;return node?scrollY+node.getBoundingClientRect().top:0};
 const travel=()=>{const node=rail.current;return node?Math.max(0,node.scrollWidth-node.clientWidth):0};
 const moveTo=(value:number,smooth=true)=>scrollTo({top:top()+Math.max(0,Math.min(travel(),value)),behavior:smooth&&!reduced?'smooth':'instant'});
 useEffect(()=>{
  const outer=section.current,node=rail.current;if(!outer||!node)return;
  let frame=0;
  const update=()=>{frame=0;const max=Math.max(0,node.scrollWidth-node.clientWidth),position=Math.max(0,Math.min(max,-outer.getBoundingClientRect().top));node.scrollLeft=position;setProgress(max?position/max:1);const cards=[...node.querySelectorAll<HTMLElement>('.archive-photo')];let nearest=0,distance=Infinity;cards.forEach((card,i)=>{const value=Math.abs(card.offsetLeft-node.offsetLeft-position);if(value<distance){distance=value;nearest=i}});setIndex(position>=max-2?photos.length-1:nearest)};
  const schedule=()=>{if(!frame)frame=requestAnimationFrame(update)};
  const resize=()=>{outer.style.setProperty('--gallery-travel',`${Math.max(0,node.scrollWidth-node.clientWidth)}px`);schedule()};
  const observer=new ResizeObserver(resize);observer.observe(node);node.querySelectorAll('.archive-photo').forEach(card=>observer.observe(card));
  const wheel=(event:WheelEvent)=>{if(event.ctrlKey||Math.abs(event.deltaX)<=Math.abs(event.deltaY))return;const max=node.scrollWidth-node.clientWidth;if((event.deltaX>0&&node.scrollLeft<max-1)||(event.deltaX<0&&node.scrollLeft>1)){event.preventDefault();moveTo(node.scrollLeft+event.deltaX*(event.deltaMode===1?16:1),false)}};
  node.addEventListener('wheel',wheel,{passive:false});addEventListener('scroll',schedule,{passive:true});addEventListener('resize',resize);resize();
  return()=>{observer.disconnect();cancelAnimationFrame(frame);node.removeEventListener('wheel',wheel);removeEventListener('scroll',schedule);removeEventListener('resize',resize)};
 },[]);
 return <section className="archive-scroll" id="photos" ref={section} aria-label="Наши моменты">
  <div className="archive section-pad gallery-sticky">
   <div className="archive-heading"><h2>Наши<br/><em>моменты.</em></h2><div><p>Листайте вниз — фотографии движутся вбок.<br/>Нажмите на кадр: внутри маленькая история.</p><div className="gallery-controls"><button onClick={()=>moveTo((rail.current?.scrollLeft||0)-(rail.current?.clientWidth||0)*.7)} disabled={progress<=0} aria-label="Предыдущие фотографии">←</button><span className="eyebrow">{String(index+1).padStart(2,'0')} / {photos.length}</span><button onClick={()=>moveTo((rail.current?.scrollLeft||0)+(rail.current?.clientWidth||0)*.7)} disabled={progress>=1} aria-label="Следующие фотографии">→</button></div></div></div>
   <div className="photo-rail" ref={rail} tabIndex={0} role="region" aria-label="Фотографии пары — листайте вниз или используйте стрелки" onKeyDown={event=>{const amount=(rail.current?.clientWidth||0)*.7,current=rail.current?.scrollLeft||0;if(['ArrowRight','ArrowLeft','Home','End'].includes(event.key)){event.preventDefault();moveTo(event.key==='Home'?0:event.key==='End'?travel():current+(event.key==='ArrowRight'?amount:-amount))}}} onPointerDown={event=>{if(event.button!==0)return;drag.current={x:event.clientX,y:event.clientY,scroll:rail.current?.scrollLeft||0};suppressClick.current=false}} onPointerMove={event=>{const start=drag.current;if(!start)return;const delta=start.x-event.clientX;if(Math.abs(delta)>8&&Math.abs(delta)>Math.abs(start.y-event.clientY)*1.2){suppressClick.current=true;event.currentTarget.setPointerCapture(event.pointerId);moveTo(start.scroll+delta,false)}}} onPointerUp={()=>{drag.current=null}} onPointerCancel={()=>{drag.current=null}} onClickCapture={event=>{if(suppressClick.current){event.preventDefault();event.stopPropagation();suppressClick.current=false}}}>
    {photos.map((photo,i)=><button className="archive-photo" key={photo.src} onClick={event=>onOpen(i,event)} onFocus={event=>{const node=rail.current;if(!node)return;const card=event.currentTarget;const left=card.offsetLeft-node.offsetLeft;if(left<node.scrollLeft-2||left+card.offsetWidth>node.scrollLeft+node.clientWidth+2)moveTo(left,false)}} aria-label={'Открыть фото и историю: '+photo.caption}><img src={photo.src} alt={photo.alt} loading="lazy" draggable={false}/><span className="photo-story-cue" aria-hidden="true"><i>↗</i><span>История кадра</span></span><span className="photo-caption">{photo.caption}<i>↗</i></span></button>)}
   </div>
   <div className="gallery-progress" aria-hidden="true"><span style={{transform:`scaleX(${progress})`}}/></div>
   <div className="archive-foot"><span className="gallery-direction">{progress>=.999?'Все кадры — дальше наш день ↓':progress<=.001?'Листайте вниз, чтобы увидеть все кадры ↓':'Продолжайте листать ↓'}</span><button className="cat-trigger" onClick={()=>setCat(value=>!value)} aria-expanded={cat}>А кто здесь главный? <span>✳</span></button></div>
   {cat&&<div className="cat-easter" role="status"><button className="cat-close" onClick={()=>setCat(false)} aria-label="Закрыть историю о котах">×</button><span>ЧЕРНЫШ × ГРЕТТА</span><p>У нас два кота.<br/>И своё мнение о том, кто у кого живёт.</p></div>}
  </div>
 </section>
}
