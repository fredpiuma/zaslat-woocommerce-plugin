# Zaslat Shipping for WooCommerce `v1.2.0`

A WooCommerce shipping method plugin that fetches real-time quotes from the [Zaslat](https://www.zaslat.cz) API and presents them as selectable shipping options at checkout.

## Features

- **Live quotes** — calls the Zaslat API on checkout and displays available carriers (PPL, DPD, GLS, etc.) with prices and estimated delivery dates
- **Smart caching** — quotes are cached for 3 days via WordPress transients; a permanent fallback copy is kept so customers still see options if the API is temporarily unreachable
- **Currency conversion** — rates are fetched in CZK and converted to USD using live exchange rates from [Frankfurter](https://www.frankfurter.app), with a 2-day cache to avoid unnecessary requests
- **Shipping zones** — appears natively in WooCommerce shipping zones, just like any built-in method
- **Sensible defaults** — products without dimensions/weight configured fall back to `160 × 70 × 40 cm / 27 kg`

## Requirements

- PHP 7.4+
- WordPress 5.8+
- WooCommerce (any recent version)

## Installation

1. Clone or download this repository into your WordPress plugins directory:
   ```
   wp-content/plugins/zaslat-shipping/
   ```
2. Activate **Zaslat Shipping** in **WP Admin → Plugins**.
3. Go to **WooCommerce → Zaslat Shipping** and enter your API key.
4. Go to **WooCommerce → Settings → Shipping → Zones**, open a zone, click **Add shipping method**, and select **Zaslat**.

## Configuration

| Setting | Location | Description |
|---------|----------|-------------|
| API Key | WooCommerce → Zaslat Shipping | Your API key from [zaslat.cz](https://www.zaslat.cz) |
| Method title | Per-zone instance settings | Label shown to customers at checkout |

Default package dimensions are hardcoded and require no configuration:

| Dimension | Default |
|-----------|---------|
| Length    | 160 cm  |
| Width     | 70 cm   |
| Height    | 40 cm   |
| Weight    | 27 kg   |

## Testing

A integration test suite is included. Tests make real calls to the Zaslat API.

```bash
# Quick runner (no extensions required)
php tests/run.php

# PHPUnit (requires php-xml extension)
composer install
./vendor/bin/phpunit
```

Set your API key via env variable if needed:
```bash
ZASLAT_API_KEY=your-key php tests/run.php
```

## File Structure

```
zaslat-shipping/
├── zaslat-shipping.php        # Plugin entry point — autoloader + hook registration
└── src/
    ├── Shipping_Method.php    # WC_Shipping_Method — calculate_shipping(), package building
    ├── Zaslat_API.php         # HTTP client for the Zaslat rates endpoint
    ├── Rate_Cache.php         # Transient cache with persistent fallback
    ├── Currency_Converter.php # CZK → USD conversion with 2-day cache
    └── Global_Settings.php    # WooCommerce settings page (API key)
```

## How It Works

```
Customer fills in checkout address
  └─▶ WooCommerce calls calculate_shipping()
        ├─▶ build_packages() — reads product weight/dims, falls back to defaults
        ├─▶ Rate_Cache::get() — cache hit? use it
        │     └─▶ miss → Zaslat_API::get_rates() — POST to Zaslat API
        │               ├─▶ success → cache it, display rates
        │               └─▶ failure → Rate_Cache::get_fallback() — last known rates
        └─▶ Currency_Converter::czk_to_usd() — per rate
              └─▶ add_rate() — one WooCommerce shipping option per selectable carrier
```

## Authors

**Frederico Castro** — Senior Web Developer & Software Architect with 17+ years of experience specializing in PHP, WooCommerce, and high-complexity e-commerce ecosystems.

- 🌍 [fredericodecastro.com.br](https://www.fredericodecastro.com.br)
- 💼 [LinkedIn](https://www.linkedin.com/in/fredericodecastro/)
- 💬 [WhatsApp](https://wa.me/551114636944) · [Telegram](https://t.me/fredericomdecastro)

**Samuel Zuqui** — Computer Science student at Universidade Vila Velha, focused on mastering computing fundamentals through Python, Git, MySQL, JavaScript, CSS3, and HTML5.

- 💼 [LinkedIn](https://www.linkedin.com/in/samuelzuquij/)
- 📸 [Instagram](https://www.instagram.com/samuelzuquij/)
- 📧 [samuelzuquij@gmail.com](mailto:samuelzuquij@gmail.com)

## License

GPLv2 or later.

---

---

# Zaslat Shipping para WooCommerce `v1.2.0`

Plugin de método de envio para WooCommerce que busca cotações em tempo real na API da [Zaslat](https://www.zaslat.cz) e as apresenta como opções de frete selecionáveis no checkout.

## Funcionalidades

- **Cotações ao vivo** — consulta a API da Zaslat no checkout e exibe as transportadoras disponíveis (PPL, DPD, GLS, UPS, TOPTRANS etc.) com preços e datas estimadas de entrega
- **Cache inteligente** — cotações armazenadas por 3 dias via transients do WordPress; uma cópia permanente de fallback garante que os clientes sempre vejam opções mesmo se a API estiver temporariamente indisponível
- **Conversão de moeda** — as tarifas são obtidas em CZK e convertidas para USD usando taxas de câmbio em tempo real do [Frankfurter](https://www.frankfurter.app), com cache de 2 dias para evitar requisições desnecessárias
- **Zonas de envio** — aparece nativamente nas zonas de envio do WooCommerce, como qualquer método nativo
- **Dimensões padrão** — produtos sem peso/dimensões configurados usam `160 × 70 × 40 cm / 27 kg` como fallback

## Requisitos

- PHP 7.4+
- WordPress 5.8+
- WooCommerce (qualquer versão recente)

## Instalação

1. Clone ou baixe este repositório no diretório de plugins do WordPress:
   ```
   wp-content/plugins/zaslat-shipping/
   ```
2. Ative o **Zaslat Shipping** em **WP Admin → Plugins**.
3. Acesse **WooCommerce → Zaslat Shipping** e insira sua chave de API.
4. Acesse **WooCommerce → Configurações → Envio → Zonas**, abra uma zona, clique em **Adicionar método de envio** e selecione **Zaslat**.

## Configuração

| Configuração | Local | Descrição |
|---|---|---|
| Chave de API | WooCommerce → Zaslat Shipping | Sua chave de API do [zaslat.cz](https://www.zaslat.cz) |
| Título do método | Configurações por zona | Rótulo exibido aos clientes no checkout |

As dimensões padrão do pacote são fixas e não requerem configuração:

| Dimensão | Padrão |
|---|---|
| Comprimento | 160 cm |
| Largura | 70 cm |
| Altura | 40 cm |
| Peso | 27 kg |

## Testes

A suíte de testes de integração está incluída. Os testes fazem chamadas reais à API da Zaslat.

```bash
# Runner rápido (sem extensões PHP adicionais)
php tests/run.php

# PHPUnit (requer a extensão php-xml)
composer install
./vendor/bin/phpunit
```

Defina sua chave de API via variável de ambiente se necessário:
```bash
ZASLAT_API_KEY=sua-chave php tests/run.php
```

## Estrutura de Arquivos

```
zaslat-shipping/
├── zaslat-shipping.php        # Ponto de entrada do plugin — autoloader + registro de hooks
└── src/
    ├── Shipping_Method.php    # WC_Shipping_Method — calculate_shipping(), montagem dos pacotes
    ├── Zaslat_API.php         # Cliente HTTP para o endpoint de tarifas da Zaslat
    ├── Rate_Cache.php         # Cache via transients com fallback permanente
    ├── Currency_Converter.php # Conversão CZK → USD com cache de 2 dias
    └── Global_Settings.php    # Página de configurações no WooCommerce (chave de API)
```

## Como Funciona

```
Cliente preenche o endereço no checkout
  └─▶ WooCommerce chama calculate_shipping()
        ├─▶ build_packages() — lê peso/dimensões do produto, usa padrões como fallback
        ├─▶ Rate_Cache::get() — cache hit? usa o cache
        │     └─▶ miss → Zaslat_API::get_rates() — tenta até 4 dias úteis consecutivos
        │               ├─▶ sucesso → salva no cache, exibe tarifas
        │               └─▶ falha → Rate_Cache::get_fallback() — última cotação conhecida
        └─▶ Currency_Converter::czk_to_usd() — por tarifa
              └─▶ add_rate() — uma opção de envio no WooCommerce por transportadora selecionável
```

## Autores

**Frederico Castro** — Desenvolvedor Web Sênior & Arquiteto de Software com mais de 17 anos de experiência especializado em PHP, WooCommerce e ecossistemas de e-commerce de alta complexidade.

- 🌍 [fredericodecastro.com.br](https://www.fredericodecastro.com.br)
- 💼 [LinkedIn](https://www.linkedin.com/in/fredericodecastro/)
- 💬 [WhatsApp](https://wa.me/551114636944) · [Telegram](https://t.me/fredericomdecastro)

**Samuel Zuqui** — Estudante de Ciência da Computação na Universidade Vila Velha, focado em dominar os fundamentos da computação através de Python, Git, MySQL, JavaScript, CSS3 e HTML5.

- 💼 [LinkedIn](https://www.linkedin.com/in/samuelzuquij/)
- 📸 [Instagram](https://www.instagram.com/samuelzuquij/)
- 📧 [samuelzuquij@gmail.com](mailto:samuelzuquij@gmail.com)

## Licença

GPLv2 ou posterior.
