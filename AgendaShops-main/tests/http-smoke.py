"""End-to-end smoke test; creates and drops its own database, requires PHP + Python."""
import os, re, subprocess, time, socket, urllib.request, urllib.parse, urllib.error, http.cookiejar, secrets, json
from pathlib import Path
ROOT=Path(__file__).resolve().parent.parent
PHP=os.environ.get('PHP_BINARY',r'C:\xampp\php\php.exe' if os.name=='nt' else 'php')
env=os.environ.copy();env['DB_DATABASE']='agendashops_http_'+secrets.token_hex(5)
with socket.socket() as s: s.bind(('127.0.0.1',0));port=s.getsockname()[1]
base=f'http://127.0.0.1:{port}/';env['APP_URL']=base.rstrip('/')
flags=subprocess.CREATE_NO_WINDOW if os.name=='nt' else 0
subprocess.run([PHP,'tests/http-fixture.php'],cwd=ROOT,env=env,check=True,creationflags=flags)
server=subprocess.Popen([PHP,'-S',f'127.0.0.1:{port}','-t','public','public/router.php'],cwd=ROOT,env=env,stdout=subprocess.DEVNULL,stderr=subprocess.DEVNULL,creationflags=flags)
def browser():return urllib.request.build_opener(urllib.request.HTTPCookieProcessor(http.cookiejar.CookieJar()))
b=browser();passed=0
def req(path,data=None,client=None):
    request=urllib.request.Request(base+path,urllib.parse.urlencode(data,doseq=True).encode() if data is not None else None)
    try:
        with (client or b).open(request,timeout=15) as r:return r.status,r.read().decode(),r.geturl()
    except urllib.error.HTTPError as e:return e.code,e.read().decode(),e.geturl()
def csrf(html):return re.search(r'name="_csrf" value="([a-f0-9]+)"',html).group(1)
def check(value,label):
    global passed
    if not value:raise AssertionError(label)
    print('OK · '+label);passed+=1
try:
    for _ in range(60):
        try:req('');break
        except OSError:time.sleep(.1)
    status,html,_=req('');check(status==200 and 'Seu talento' in html,'landing page')
    check(req('painel')[2].endswith('/entrar'),'painel exige autenticação')
    status,html,_=req('cadastrar');token=csrf(html)
    check(req('cadastrar',{'email':'invalid'})[0]==419,'CSRF obrigatório')
    register={'_csrf':token,'establishment':'Estúdio HTTP','name':'Pessoa Teste','phone':'11999999999','email':'owner@test.local','password':'Testpassword123','category':'Estética','privacy':'on'}
    status,html,_=req('cadastrar',register);check(status==200 and 'Próximos atendimentos' in html,'cadastro cria painel real')
    token=csrf(html);slug=re.search(r'/agendar/([a-z0-9-]+)',html).group(1)
    req('painel/profissionais',{'_csrf':token,'action':'professional','name':'Profissional HTTP','specialty':'Estética','active':'on','email':'pro@test.local','password':'Testpassword123'})
    status,html,_=req('painel/profissionais');check(status==200 and 'Profissional HTTP' in html,'cadastro de profissional')
    req('painel/servicos',{'_csrf':token,'action':'service','name':'Atendimento HTTP','price':'80','duration':'30','buffer':'0','active':'on','professionals[]':'1'})
    status,html,_=req('painel/servicos');check(status==200 and 'Atendimento HTTP' in html,'cadastro de serviço e vínculo')
    for route in ['agenda','agendamentos','horarios','clientes','configuracoes']:
        check(req('painel/'+route)[0]==200,'rota administrativa '+route)
    public=browser();status,html,_=req('agendar/'+slug,client=public);check(status==200 and 'Atendimento HTTP' in html,'catálogo público real')
    publictoken=csrf(html)
    import datetime
    day=datetime.date.today()+datetime.timedelta(days=1)
    while day.weekday()==6:day+=datetime.timedelta(days=1)
    date=day.isoformat()
    status,body,_=req('api/disponibilidade/'+slug+'?'+urllib.parse.urlencode({'service':1,'professional':1,'date':date}),client=public)
    slots=json.loads(body);check(status==200 and len(slots)>0,'API de disponibilidade')
    booking={'_csrf':publictoken,'service':'1','professional':'1','date':date,'time':slots[0]['time'],'name':'Cliente HTTP','phone':'11911112222','privacy':'on'}
    status,body,_=req('api/reservar/'+slug,booking,public);result=json.loads(body);check(status==200 and 'redirect' in result,'reserva pública sem conta')
    status,html,_=req(result['redirect'].replace(base,''),client=public);check(status==200 and 'Agendamento recebido!' in html,'confirmação da reserva')
    ics=re.search(r'/calendario/([a-f0-9]{64})',html).group(1)
    check('BEGIN:VEVENT' in req('calendario/'+ics,client=public)[1],'arquivo ICS')
    check(req('api/reservar/'+slug,booking,public)[0]==409,'segunda reserva no mesmo horário rejeitada')
    status,html,_=req('painel/agenda?date='+date);check('Cliente HTTP' in html,'reserva aparece na agenda')
    second=browser();_,html,_=req('cadastrar',client=second);other=dict(register,_csrf=csrf(html),establishment='Outra empresa',email='other@test.local')
    status,html,_=req('cadastrar',other,second);check(status==200,'segunda empresa cadastrada')
    check('Atendimento HTTP' not in req('painel/servicos',client=second)[1],'serviços isolados por empresa')
    check('Cliente HTTP' not in req('painel/clientes',client=second)[1],'clientes isolados por empresa')
    check(req('painel/agendamentos?reschedule=1',client=second)[0]==404,'ID de reserva alheia recusado')
    pro=browser();_,html,_=req('entrar',client=pro);status,html,_=req('entrar',{'_csrf':csrf(html),'email':'pro@test.local','password':'Testpassword123'},pro)
    check(status==200 and 'Próximos atendimentos' in html,'login do profissional')
    check(req('painel/servicos',client=pro)[0]==403,'profissional não administra serviços')
    check(req('plataforma')[0]==403,'proprietário não acessa superadmin')
    print(f'\n{passed} verificações HTTP aprovadas.')
finally:
    server.terminate();server.wait(timeout=10)
    subprocess.run([PHP,'tests/http-fixture.php','cleanup'],cwd=ROOT,env=env,check=True,creationflags=flags)
