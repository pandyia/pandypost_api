# Autenticação das requisições

Para autenticar, envie o header **`Authorization`** com o valor **`"Bearer {TOKEN}"`**.

Os endpoints que exigem login têm o selo `requires authentication`. Os demais são públicos.

Obtenha o token em <code>POST /api/login</code> (campo <code>token</code>). Ele vale até o logout ou a expiração definida pelo <code>remember_me</code>.
