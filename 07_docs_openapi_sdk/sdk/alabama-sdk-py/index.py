"""
Alabama SDK Python - cliente mínimo para a Test Prompt API.

Observação importante (formato de resposta):
A API responde no formato padronizado (ApiResponse):
    {"ok": bool, "data": any, "error": {"code","message"}|null, "meta": object}

Exemplo de uso (simples):

    import sys
    sys.path.append("/caminho/para/07_docs_openapi_sdk/sdk/alabama-sdk-py")
    from index import AlabamaClient

    client = AlabamaClient(base_url="http://localhost:8000")
    res = client.test_prompt("Olá, IA!")
    print(res["data"]["answer"])

Variáveis de ambiente:
    - ALABAMA_API_BASE_URL: base URL da API (default: http://localhost:8000)
"""

import json
import os
import urllib.request
from urllib.error import HTTPError, URLError
from dataclasses import dataclass
from typing import Any, Dict, Optional


@dataclass
class AlabamaClient:
    base_url: Optional[str] = None
    timeout: int = 30

    def __post_init__(self) -> None:
        env = os.getenv("ALABAMA_API_BASE_URL")
        if not self.base_url:
            self.base_url = env or "http://localhost:8000"
        self.base_url = self.base_url.rstrip("/")

    def _decode_json(self, body: str) -> Dict[str, Any]:
        try:
            decoded = json.loads(body) if body else {}
        except json.JSONDecodeError as exc:
            raise RuntimeError(f"Resposta inválida da API (não-JSON): {body}") from exc

        if not isinstance(decoded, dict):
            raise RuntimeError(f"Resposta inválida da API (esperado objeto JSON): {body}")

        return decoded

    def _extract_error_message(self, decoded: Dict[str, Any], fallback: str = "") -> str:
        # Formato atual (ApiResponse)
        err = decoded.get("error")
        if isinstance(err, dict):
            return str(err.get("message") or err.get("code") or fallback)

        # Compat (legado)
        if isinstance(decoded.get("erro"), str):
            return decoded["erro"]
        if isinstance(decoded.get("message"), str):
            return decoded["message"]

        return fallback

    def _post_json(self, path: str, payload: Dict[str, Any]) -> Dict[str, Any]:
        url = f"{self.base_url}{path}"
        data = json.dumps(payload).encode("utf-8")

        req = urllib.request.Request(url, data=data, method="POST")
        req.add_header("Content-Type", "application/json")
        req.add_header("Accept", "application/json")

        try:
            with urllib.request.urlopen(req, timeout=self.timeout) as resp:  # nosec B310 - uso deliberado para SDK simples
                body = resp.read().decode("utf-8", errors="replace")
        except HTTPError as e:
            raw = e.read().decode("utf-8", errors="replace")
            try:
                decoded = self._decode_json(raw)
                msg = self._extract_error_message(decoded, fallback=raw)
            except Exception:
                msg = raw or f"HTTP {e.code}"
            raise RuntimeError(f"Erro da API (HTTP {e.code}): {msg}") from e
        except URLError as e:
            raise RuntimeError(f"Erro de rede ao chamar API: {e}") from e

        decoded = self._decode_json(body)

        # Alguns endpoints podem retornar ok=false com status 200
        if decoded.get("ok") is False:
            msg = self._extract_error_message(decoded, fallback="Erro desconhecido")
            raise RuntimeError(f"Erro da API: {msg}")

        return decoded

    def test_prompt(
        self,
        prompt: str,
        temperature: float = 0.2,
        max_tokens: int = 256,
        model: Optional[str] = None,
    ) -> Dict[str, Any]:
        payload: Dict[str, Any] = {
            "prompt": prompt,
            "temperature": temperature,
            "max_tokens": max_tokens,
        }
        if model is not None:
            payload["model"] = model

        return self._post_json("/api/test_prompt.php", payload)
