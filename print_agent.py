#!/usr/bin/env python3
"""Agent SURA : récupère les tickets depuis l'hébergement et imprime en ESC/POS."""
import json,time,urllib.request,urllib.error,os,sys,textwrap,logging
BASE=os.path.dirname(os.path.abspath(__file__))
with open(os.path.join(BASE,'config.json'),encoding='utf-8') as f: CFG=json.load(f)
URL=CFG['server_url'].rstrip('/')+'/api/print/agent/'
TOKEN=CFG['agent_token']; DEVICE=CFG.get('printer_device','/dev/usb/lp4'); POLL=max(1,int(CFG.get('poll_seconds',3))); WIDTH=int(CFG.get('paper_width',48))
logging.basicConfig(level=logging.INFO,format='%(asctime)s %(levelname)s %(message)s')
def req(path,data=None):
 body=None if data is None else json.dumps(data,ensure_ascii=False).encode()
 r=urllib.request.Request(URL+path,data=body,headers={'Authorization':'Bearer '+TOKEN,'Content-Type':'application/json'})
 with urllib.request.urlopen(r,timeout=25) as x:return json.loads(x.read().decode())
def money(v):return f"{float(v):,.0f}".replace(',',' ')+' Ar'
def fit(s):return str(s or '').encode('cp858','replace').decode('cp858')
def line(s=''):return (fit(s)+'\n').encode('cp858','replace')
def ticket(p):
 out=bytearray(b'\x1b@');out+=b'\x1ba\x01';out+=b'\x1bE\x01';out+=line('SURA RESTAURANT');out+=b'\x1bE\x00';out+=line('Korean & Malagasy Restaurant');out+=line('Olympia Tanjombato');out+=line('MVola : 038 24 541 74');out+=line('-'*WIDTH);out+=b'\x1ba\x00';out+=line('Facture : '+p['invoice_number']);out+=line('Date : '+str(p['created_at']));out+=line('Commande : #'+str(p['order_id'])+' / '+str(p['order_type']))
 if p.get('order_type')=='dine_in':out+=line('Table : '+str(p.get('table_no') or '-'))
 else:
  out+=line('Client : '+str(p.get('client_name') or '-'))
  if p.get('client_phone'):out+=line('Tel : '+str(p['client_phone']))
  if p.get('client_address'):out+=line('Adresse : '+str(p['client_address']))
 out+=line('-'*WIDTH)
 for it in p.get('items',[]):
  name=str(it['item_name'])
  for part in textwrap.wrap(name,width=WIDTH):out+=line(part)
  left=f"{int(it['quantity'])} x {money(it['unit_price'])}";right=money(it['subtotal']);out+=line(left+' '*(max(1,WIDTH-len(left)-len(right)))+right)
 out+=line('-'*WIDTH);out+=b'\x1bE\x01';total='TOTAL : '+money(p['total']);out+=line(total);out+=b'\x1bE\x00';out+=line('Paiement : '+str(p.get('payment_status') or '-'));out+=line('Mode : '+str(p.get('payment_method') or '-'))
 if p.get('payer_name'):out+=line('Payeur : '+str(p['payer_name']))
 if p.get('payer_phone'):out+=line('Tel payeur : '+str(p['payer_phone']))
 out+=line('-'*WIDTH);out+=b'\x1ba\x01'
 for x in p.get('footer',[]):out+=line(x)
 out+=line('SURA - Merci !');out+=b'\n'*6+b'\x1dV\x00';return bytes(out)
def print_job(payload):
 data=ticket(payload)
 with open(DEVICE,'wb',buffering=0) as pr:pr.write(data)
while True:
 try:
  d=req('next.php')
  job=d.get('job')
  if job:
   jid=job['job_id'];logging.info('Impression %s',jid)
   try:
    print_job(job['payload'])
    req('complete.php',{'job_id':jid,'success':True})
    logging.info('Ticket imprimé')
   except Exception as e:
    logging.exception('Erreur impression');
    try:req('complete.php',{'job_id':jid,'success':False,'error':str(e)})
    except Exception:logging.exception('Impossible de signaler erreur')
  else:time.sleep(POLL)
 except KeyboardInterrupt:break
 except Exception as e:logging.error('Connexion serveur/imprimante : %s',e);time.sleep(max(POLL,5))
