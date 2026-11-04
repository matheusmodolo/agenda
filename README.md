# Agenda de Máquinas
> **Fase atual:** a parte das regras (o "cérebro" do sistema) está pronta e testada. As rotas da API e a tela em Angular ainda não foram feitas.

---

## O que já foi feito

### 1. O banco de dados

Hoje o sistema guarda três coisas:

| Tabela       | O que guarda                                                                 |
|--------------|------------------------------------------------------------------------------|
| `maquinas`   | Nome, tipo (trator, pulverizador ou colheitadeira) e se está ativa           |
| `clientes`   | Nome do cliente                                                              |
| `reservas`   | Qual máquina, qual cliente, início, fim e a situação (status) da reserva     |

Também tem um *seeder* que cria um cliente, uma máquina e uma reserva de exemplo, para não começar com o banco vazio.

### 2. As regras de reserva

Todas as regras estão num lugar só: o [`ReservaService`](backend/app/Services/ReservaService.php).

**Para criar uma reserva:**

- O fim precisa ser depois do início. Fim igual ao início também não vale, porque seria uma reserva de zero minutos.
- Não dá para reservar no passado. Começar exatamente "agora" pode.
- A reserva pode durar no máximo 7 dias. Exatamente 7 dias pode.
- A máquina não pode estar reservada por outra pessoa no mesmo período.
- Toda reserva começa como Pendente, mesmo que alguém tente mandar outro status.

**Como funciona a regra de "horário ocupado":**

Duas reservas batem quando uma começa antes da outra terminar E termina depois da outra começar. Essa única conta cobre todos os casos: a reserva que pega só o começo, só o final, que fica no meio, que engloba a outra ou que é idêntica.

Alguns detalhes:

- Reservas canceladas ou concluídas liberam o horário.
- Reservar o mesmo horário em outra máquina é permitido.

### 3. A vida de uma reserva (status)

Uma reserva passa por estes estados:

- **Pendente** pode virar Confirmada ou Cancelada.
- **Confirmada** pode virar Concluída ou Cancelada.
- **Cancelada** e **Concluída** são o fim da linha: não mudam mais.

Mais duas regras que dependem do relógio:

- Só dá para concluir uma reserva depois que o horário dela terminou.
- Uma reserva confirmada só pode ser cancelada com pelo menos 24 horas de antecedência. Uma pendente pode ser cancelada a qualquer momento.

### 4. Mensagens de erro claras

Quando algo dá errado, o sistema responde com uma mensagem em português e um código fixo. Por exemplo:

```json
{
  "message": "Conflito de horário: a máquina \"Trator 01\" já está reservada de 10/11/2026 10:00 até 10/11/2026 12:00 (reserva #3, Pendente).",
  "codigo": "conflito_de_horario",
  "reserva_conflitante_id": 3,
  "sugestao": null
}
```

A mensagem é para a pessoa ler. O `codigo` é para o front-end (ou um teste) saber o que aconteceu sem depender do texto.

---

## Como rodar

**Necessário:**

- PHP 8.3 ou mais novo
- Composer
- Node 20.19+ ou 22+ (só para o front-end, quando ele existir)

O banco usado é o **SQLite**.

```bash
cd backend
composer install
cp .env.example .env
php artisan key:generate
php artisan migrate --seed
php artisan serve # http://localhost:8000
```

---

## Testes

Para rodar:

```bash
cd backend
php artisan test
```

Os testes "congelam" o relógio numa data fixa, então o resultado é sempre o mesmo, não importa o dia em que você rode.

O que está sendo testado hoje (em [`CriacaoDeReservaTest`](backend/tests/Feature/CriacaoDeReservaTest.php)):

**Horários que encostam, mas não batem (devem ser aceitos)**
- Reserva que termina exatamente quando a outra começa
- Reserva que começa exatamente quando a outra termina
- Mesmo horário de uma reserva que foi cancelada
- Mesmo horário, mas em outra máquina

**Horários que batem (devem ser recusados)**

Imaginando uma reserva já existente das 10h às 12h:
- Pega só o começo (9h às 11h)
- Pega só o final (11h às 13h)
- Fica no meio (10h30 às 11h30)
- Engloba a reserva toda (9h às 13h)
- Exatamente o mesmo horário (10h às 12h)
- Sobra só um minuto (11h59 às 14h)
- Reserva confirmada também bloqueia o horário

**Período da reserva**
- Fim antes do início é recusado
- Fim igual ao início é recusado
- Início no passado é recusado
- Início exatamente agora é aceito
- Mais de 7 dias é recusado
- Exatamente 7 dias é aceito

**Outros**
- Data em UTC (como o JavaScript costuma mandar) é convertida para o horário de Brasília
- Se alguém mandar um status, ele é ignorado: toda reserva nasce Pendente

---

## Decisões técnicas

### Regras no Service

Todas as regras ficam no `ReservaService`, e não espalhadas pelos controllers. Assim:

- dá para testar as regras sem precisar simular uma requisição HTTP;
- se amanhã a reserva puder ser criada por outro caminho (um comando, uma fila), a regra é a mesma;
- mais fácil de encontrar.

### Status como Enum

Os status (Pendente, Confirmada, Cancelada, Concluída) são um *enum* do PHP, e não textos soltos. Isso evita erro de digitação e deixa a regra de "pode mudar de X para Y" dentro do próprio enum, num lugar só e fácil de ler.

### 409 ou 422?

O sistema usa dois códigos de erro diferentes:

- **409 (Conflito):** o pedido está correto, mas o horário já está ocupado. Se a outra reserva for cancelada, o mesmo pedido passa a funcionar.
- **422 (Dados inválidos):** o pedido em si tem problema, como fim antes do início, data no passado ou uma mudança de status que não existe. Esse não vai funcionar nunca, precisa ser corrigido.

Isso ajuda o front-end a mostrar a mensagem certa para cada caso.

### Fuso horário

O sistema trabalha no horário de Brasília (`America/Sao_Paulo`), configurado no `.env`.

O navegador costuma mandar as datas em UTC (por exemplo, `2026-11-10T13:00:00Z`). O sistema converte para o horário local **antes** de comparar ou salvar. Sem isso, uma reserva das 10h poderia ser gravada como 13h, e a checagem de conflito daria resultado errado.

---

## Limitações e o que eu faria com mais tempo

- **Duas pessoas reservando ao mesmo tempo:** hoje, se dois pedidos para o mesmo horário chegarem exatamente juntos, os dois podem passar na checagem. A solução é fazer a checagem e a gravação dentro de uma transação, travando as linhas com `lockForUpdate`.
- **Sugerir o próximo horário livre:** quando houver conflito, mostrar ao cliente o próximo horário disponível com a mesma duração. Já comecei, mas ainda não está pronto.
- **API e tela:** criar as rotas da API e a interface em Angular (a pasta `frontend` ainda tem só o projeto inicial).
- **Paginação** na listagem de reservas.
- **Testes do front-end.**
- **Testes das mudanças de status** (as regras já existem, mas ainda não têm testes próprios).
