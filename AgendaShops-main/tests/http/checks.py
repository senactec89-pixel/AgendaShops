"""End-to-end HTTP checks. Uses an isolated temporary database and local mail sink."""
import base64
import html
import http.cookiejar
import json
import os
from pathlib import Path
import re
import socket
import subprocess
import tempfile
import time
import urllib.error
import urllib.parse
import urllib.request

ROOT = Path(__file__).resolve().parents[2]
PHP = os.environ.get('PHP_BINARY', r'C:\xampp\php\php.exe' if os.name == 'nt' else 'php')
checks = 0

def check(value, message):
    global checks
    checks += 1
    if not value:
        raise AssertionError(message)

class Client:
    def __init__(self, base):
        self.base = base
        self.opener = urllib.request.build_opener(urllib.request.HTTPCookieProcessor(http.cookiejar.CookieJar()))
        self.body = ''
        self.url = ''

    def request(self, path, data=None, headers=None, expected=200):
        if isinstance(data, dict):
            data = urllib.parse.urlencode(data, doseq=True).encode()
        request = urllib.request.Request(self.base + path, data=data, headers=headers or {})
        try:
            response = self.opener.open(request, timeout=20)
        except urllib.error.HTTPError as error:
            response = error
        raw = response.read()
        self.body = raw.decode('utf-8', errors='replace')
        self.url = response.geturl()
        check(response.status == expected, f'{path}: expected {expected}, got {response.status}: {self.body[:300]}')
        return self.body

    def get(self, path, expected=200):
        return self.request(path, expected=expected)

    def post(self, action, data=None, expected=200, page='/perfil'):
        self.get(page)
        token = re.search(r'name="csrf" value="([a-f0-9]+)"', self.body)
        check(token is not None, 'CSRF token rendered')
        payload = {'csrf': token.group(1), 'return': page}
        payload.update(data or {})
        return self.request('/acao/' + action, payload, expected=expected)

def hidden(body, action, name):
    form = re.search(r'<form[^>]+action="/acao/' + re.escape(action) + r'".*?</form>', body, re.S)
    check(form is not None, f'Form {action} exists')
    match = re.search(r'name="' + re.escape(name) + r'" value="([^"]*)"', form.group(0))
    check(match is not None, f'Field {name} exists')
    return html.unescape(match.group(1))

with tempfile.TemporaryDirectory(prefix='agendashops-http-') as temporary:
    environment = os.environ.copy()
    environment.update(DB_DSN='sqlite:' + str(Path(temporary) / 'site.sqlite'), APP_ENV='test', STORAGE_PATH=str(Path(temporary) / 'storage'), MAIL_TRANSPORT='file', MAIL_PATH=str(Path(temporary) / 'mail'), GOOGLE_CLIENT_ID='', GOOGLE_CLIENT_SECRET='')
    fixture = subprocess.run([PHP, 'tests/http/fixture.php'], cwd=ROOT, env=environment, capture_output=True, check=True, text=True)
    admin_credentials = json.loads(fixture.stdout)
    with socket.socket() as sock:
        sock.bind(('127.0.0.1', 0))
        port = sock.getsockname()[1]
    base = f'http://127.0.0.1:{port}'
    environment['APP_URL'] = base
    log = open(Path(temporary) / 'server.log', 'w+', encoding='utf-8')
    server_command = [PHP, '-S', f'127.0.0.1:{port}'] + (['-t', 'public', 'public/index.php'] if os.environ.get('HTTP_ENTRY') == 'public' else ['index.php'])
    server = subprocess.Popen(server_command, cwd=ROOT, env=environment, stdout=log, stderr=log, creationflags=subprocess.CREATE_NO_WINDOW if os.name == 'nt' else 0)
    try:
        for attempt in range(60):
            try:
                with socket.create_connection(('127.0.0.1', port), timeout=.2):
                    break
            except OSError:
                time.sleep(.1)
        public, owner, customer, admin = [Client(base) for _ in range(4)]
        check('AgendaShops' in public.get('/'), 'Homepage works')
        public.get('/index.php')
        public.get('/explorar')
        public.get('/perfil', 401)
        public.request('/acao/register', {'name': 'Invalid'}, expected=419)
        public.get('/.env', 404)
        public.get('/storage/agendashops.sqlite', 404)
        public.get('/src/Http/Application.php', 404)
        public.get('/config/local.php', 404)
        public.get('/docs/02-functional-requirements.md', 404)
        public.get('/composer.json', 404)
        public.get('/config/app.php', 404)
        public.get('/database/schema.sql', 404)
        public.get('/templates/layouts/main.php', 404)
        public.get('/api/v1/missing', 404)
        check(json.loads(public.get('/api/v1/health'))['status'] == 'ok', 'Health JSON')
        public.request('/api/v1/health', {}, expected=405)
        public.get('/assets/css/app.css')
        public.get('/assets/js/app.js')
        for client, name, phone in [(owner, 'Owner', '11988880002'), (customer, 'Customer', '11988880003')]:
            client.post('register', {'name': name, 'email': name.lower() + '@example.test', 'phone': phone, 'password': 'Test-password-123', 'password_confirmation': 'Test-password-123', 'consent': '1'}, page='/cadastro')
            check(name in client.body, 'Registration starts session')
        owner.get('/admin', 403)
        customer.get('/admin', 403)
        admin.post('setup', {'name':'HTTP Admin','email':admin_credentials['email'],'phone':'11988880001','password':admin_credentials['password'],'password_confirmation':admin_credentials['password'],'consent':'1','setup_token':admin_credentials['setup_token']},page='/instalar?token='+admin_credentials['setup_token'])
        check('Administração' in admin.body,'First administrator configured through private setup link')
        public.get('/instalar?token='+admin_credentials['setup_token'],403)
        admin.post('category', {'name': 'Cabelo', 'sort_order': '0', 'active': '1'}, page='/admin')
        category = hidden(admin.body, 'category', 'id')
        owner.post('business-create', {'name': 'HTTP Salon', 'description': 'Salão de testes funcionais.', 'address': 'Rua de teste 123', 'city': 'São Paulo', 'phone': '1133334444', 'timezone': 'America/Sao_Paulo', 'policy': 'Avise pelo chat se precisar cancelar.'}, page='/parceiro')
        business = urllib.parse.parse_qs(urllib.parse.urlparse(owner.url).query)['business'][0]
        partner_url = '/parceiro?business=' + business
        owner.post('professional-save', {'business_id': business, 'name': 'HTTP Professional', 'opens': '09:00', 'closes': '18:00', 'weekdays[]': ['1','2','3','4','5','6','7'], 'active': '1'}, page=partner_url)
        professional = hidden(owner.body, 'professional-save', 'professional_id')
        owner.post('service-save', {'business_id': business, 'category_id': category, 'name': 'Corte HTTP', 'description': 'Serviço de corte para testar o fluxo.', 'duration_minutes': '30', 'price': '59,90', 'professionals[]': [professional], 'active': '1'}, page=partner_url)
        service = hidden(owner.body, 'service-save', 'service_id')
        check('Corte HTTP' not in public.get('/explorar'), 'Pending business hidden')
        admin.post('moderate', {'type': 'business', 'id': business, 'value': 'APPROVED'}, page='/admin')
        check('Corte HTTP' in public.get('/explorar?q=Corte'), 'Approved service discoverable')
        customer.post('service-save', {'business_id': business}, expected=403, page='/parceiro')
        owner.get(partner_url)
        slug = re.search(r'href="(/estabelecimentos/[a-z0-9-]+)"', owner.body).group(1)
        public.get(slug)
        customer.post('favorite', {'business_id': business}, page='/favoritos')
        check('HTTP Salon' in customer.body, 'Favorite displayed')
        customer.post('compare', {'service_id': service}, page='/comparar')
        check('Corte HTTP' in customer.body, 'Comparison displayed')
        extras = []
        for number in range(2,5):
            name = 'Corte adicional ' + str(number)
            owner.post('service-save', {'business_id':business,'category_id':category,'name':name,'description':'Outro serviço equivalente para comparação.','duration_minutes':'30','price':'60,00','professionals[]':[professional],'active':'1'},page=partner_url)
            form = next(form for form in re.findall(r'<form[^>]+action="/acao/service-save".*?</form>',owner.body,re.S) if f'value="{name}"' in form)
            extras.append(re.search(r'name="service_id" value="([a-f0-9]+)"',form).group(1))
        customer.post('compare',{'service_id':extras[0]},page='/comparar')
        customer.post('compare',{'service_id':extras[1]},page='/comparar')
        customer.post('compare',{'service_id':extras[2]},expected=422,page='/comparar')
        customer.post('compare',{'service_id':extras[0]},page='/comparar')
        customer.post('compare',{'service_id':extras[1]},page='/comparar')
        service_url = '/servicos/' + service
        customer.get(service_url)
        slot = html.unescape(re.search(r'name="slot" value="([^"]+)"', customer.body).group(1))
        date = slot.split('|')[1].split('T')[0]
        check(len(json.loads(public.get('/api/v1/slots/'+service+'?date='+date))) > 0,'Availability API returns live slots')
        key = hidden(customer.body, 'booking', 'request_key')
        customer.post('booking', {'service_id': service, 'slot': slot, 'request_key': key}, page=service_url)
        check('Aguardando parceiro' in customer.body, 'Request displayed as pending')
        booking = hidden(customer.body, 'transition', 'booking_id')
        owner.post('transition', {'booking_id': booking, 'status': 'CONFIRMED', 'reason': 'Aceito via site'}, page='/agendamentos')
        check('Confirmado' in owner.body, 'Partner accepted via site')
        customer.get('/agendamentos')
        conversation = re.search(r'/mensagens\?conversa=([a-f0-9]{32})', customer.body).group(1)
        chat_url = '/mensagens?conversa=' + conversation
        customer.post('message', {'conversation_id': conversation, 'body': '<script>alert(1)</script> Mensagem de teste'}, page=chat_url)
        check('&lt;script&gt;' in customer.body and '<script>alert(1)</script>' not in customer.body, 'Chat safely escapes XSS')
        owner.get(chat_url)
        check('Mensagem de teste' in owner.body, 'Partner reads customer message')
        customer.post('review', {'booking_id': booking, 'rating': '5', 'body': 'Premature review'}, expected=403, page='/agendamentos')
        customer.post('report', {'target_type': 'conversation', 'target_id': conversation, 'reason': 'Teste de denúncia da conversa.'}, page=chat_url)
        admin.get('/admin')
        check('Teste de denúncia' in admin.body, 'Admin sees report')
        admin.get('/admin/conversas/'+conversation)
        check('Mensagem de teste' in admin.body,'Admin inspects reported conversation')
        customer.get('/admin/conversas/'+conversation,403)
        admin.post('moderate', {'type': 'conversation', 'id': conversation, 'value': '1'}, page='/admin')
        customer.post('message', {'conversation_id': conversation, 'body': 'Not allowed'}, expected=403, page=chat_url)
        # Upload an actual PNG through multipart and verify it is served as an image.
        owner.get(partner_url)
        csrf = hidden(owner.body, 'upload', 'csrf')
        boundary = 'AgendaShopsTestBoundary'
        data = b''
        for name, value in {'csrf':csrf, 'return':partner_url, 'service_id':service}.items():
            data += (f'--{boundary}\r\nContent-Disposition: form-data; name="{name}"\r\n\r\n{value}\r\n').encode()
        png = base64.b64decode('iVBORw0KGgoAAAANSUhEUgAAAAEAAAABCAQAAAC1HAwCAAAAC0lEQVR42mP8/x8AAwMCAO+aF1kAAAAASUVORK5CYII=')
        data += (f'--{boundary}\r\nContent-Disposition: form-data; name="image"; filename="test.png"\r\nContent-Type: image/png\r\n\r\n').encode() + png + (f'\r\n--{boundary}--\r\n').encode()
        owner.request('/acao/upload', data, {'Content-Type':'multipart/form-data; boundary=' + boundary})
        public.get(service_url)
        image = re.search(r'src="(/media/[a-f0-9]+\.png)"',public.body).group(1)
        public.get(image)
        customer.post('transition', {'booking_id':booking, 'status':'CANCELED_BY_CUSTOMER','reason':'Cancelamento de teste'}, page='/agendamentos')
        check('Cancelado pelo cliente' in customer.body, 'Cancellation displayed')
        customer.post('forgot', {'email':'customer@example.test'}, page='/recuperar')
        mail = list((Path(temporary)/'mail').glob('*.txt'))
        check(len(mail)==1,'Recovery email generated locally')
        token = re.search(r'token=([a-f0-9]{64})',mail[0].read_text(encoding='utf-8')).group(1)
        public.post('reset', {'token':token,'password':'New-password-456','password_confirmation':'New-password-456'}, page='/redefinir?token='+token)
        customer.get('/perfil',401)
        public.post('login', {'email':'customer@example.test','password':'New-password-456'}, page='/entrar')
        public.post('reset', {'token':token,'password':'Other-password-789','password_confirmation':'Other-password-789'}, expected=422, page='/redefinir?token='+token)
        # Restart the PHP process and ensure both data and authenticated sessions persist.
        server.terminate()
        server.wait(timeout=10)
        server = subprocess.Popen(server_command, cwd=ROOT, env=environment, stdout=log, stderr=log, creationflags=subprocess.CREATE_NO_WINDOW if os.name == 'nt' else 0)
        for attempt in range(60):
            try:
                with socket.create_connection(('127.0.0.1',port),timeout=.2): break
            except OSError: time.sleep(.1)
        check('Customer' in public.get('/perfil'),'Session survives server restart')
        check('Corte HTTP' in public.get('/agendamentos'),'Bookings survive server restart')
        public.post('logout', page='/perfil')
        public.get('/perfil',401)
        public.get('/termos')
        public.get('/privacidade')
        log.flush(); log.seek(0)
        errors = log.read()
        check('PHP Warning' not in errors and 'PHP Fatal' not in errors and 'request failed' not in errors,'No runtime warnings or failures: '+errors[-2000:])
        print(f'OK: {checks} HTTP checks covering accounts, roles, catalog, booking, chat, upload, recovery and CSRF.')
    finally:
        server.terminate()
        server.wait(timeout=10)
        log.close()
