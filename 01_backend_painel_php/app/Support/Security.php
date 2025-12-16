<?php
// Lightweight security helpers: safe eval, safe exec, bearer header builder
class Security
{
    public static function is_admin(): bool
    {
        if (session_status() === PHP_SESSION_NONE) {
            @session_start();
        }

        // Compat: sinal explícito
        if (!empty($_SESSION['is_admin'])) {
            return true;
        }

        // RBAC do painel: considera Administrador como admin para ações sensíveis
        if (!empty($_SESSION['nivel_acesso'])) {
            $nivel = (string) $_SESSION['nivel_acesso'];
            if (strcasecmp($nivel, 'Administrador') === 0 || strcasecmp($nivel, 'Admin') === 0) {
                return true;
            }
        }

        // Fallback: allow when explicit env flag set (useful for CI/admin scripts)
        if (getenv('ALLOW_ADMIN_SHELL') === 'true') {
            return true;
        }

        return false;
    }

    public static function safe_exec(string $cmd): array
    {
        $res = ['output' => '', 'exit_code' => 1, 'error' => 'not allowed'];
        if (!self::is_admin()) {
            $res['error'] = 'permission denied';
            return $res;
        }
        // Require explicit opt-in via env var for executing shell commands on this server
        if (getenv('ALLOW_UNSAFE_SHELL_EXEC') !== 'true') {
            $res['error'] = 'unsafe shell execution disabled by server policy';
            return $res;
        }

        // Basic safety: disallow pipe/redirect operators and backticks
        $forbidden = ['&&', ';', '|', '`', '>', '<', '$', '{', '}', '\\'];
        foreach ($forbidden as $tok) {
            if (strpos($cmd, $tok) !== false) {
                $res['error'] = 'forbidden characters in command';
                return $res;
            }
        }

        // Use proc_open to capture stdout/stderr reliably
        $descriptorspec = [
            1 => ['pipe', 'w'],
            2 => ['pipe', 'w']
        ];
        $proc = @proc_open($cmd, $descriptorspec, $pipes);
        if (!is_resource($proc)) {
            $res['error'] = 'proc_open failed';
            return $res;
        }
        $stdout = stream_get_contents($pipes[1]);
        fclose($pipes[1]);
        $stderr = stream_get_contents($pipes[2]);
        fclose($pipes[2]);
        $status = proc_close($proc);
        $res['output'] = trim($stdout . PHP_EOL . $stderr);
        $res['exit_code'] = $status;
        $res['error'] = null;
        return $res;
    }

    public static function safe_eval(string $code)
    {
        $deny_patterns = [
            '/\beval\s*\(/i',
            '/\bexec\s*\(/i',
            '/\bsystem\s*\(/i',
            '/\bshell_exec\s*\(/i',
            '/\bpassthru\s*\(/i',
            '/\bpopen\s*\(/i',
            '/`.*`/s',
            '/proc_open\s*\(/i',
            '/preg_replace\s*\(.*e.*\)/i',
            '/\$[A-Za-z_]/',
            '/->/',
            '/\\/'
        ];
        foreach ($deny_patterns as $p) {
            if (preg_match($p, $code)) {
                error_log('Security::safe_eval blocked code: pattern ' . $p . ' in ' . $code);
                throw new \Exception('Unsafe code blocked by security policy');
            }
        }

        // Normalize common wrapping `return (bool)(...);` or trailing semicolons
        $expr = trim($code);
        $expr = preg_replace('/^return\s*\(bool\)\s*/i', '', $expr);
        $expr = preg_replace('/;\s*$/', '', $expr);
        $expr = trim($expr);

        if ($expr === '') {
            return null;
        }

        try {
            return self::evaluate_boolean_expression($expr);
        } catch (\Exception $e) {
            error_log('Security::safe_eval exception: ' . $e->getMessage());
            return null;
        }
    }

    // Very small, restricted expression evaluator supporting numbers, single-quoted strings,
    // comparisons (==, !=, ===, !==, <, >, <=, >=), arithmetic (+ - * / %), and logical && ||.
    private static function evaluate_boolean_expression(string $expr)
    {
        // Tokenize
        $tokens = [];
        $pattern = '/(' .
            "'(?:[^'\\\\]|\\\\.)*'" . // single-quoted strings
            '|\d+\.?\d*' .                 // numbers
            '|===|!==|==|!=|<=|>=|&&|\|\||[()<>+\-\*\/%]' .
            '|\btrue\b|\bfalse\b|\bnull\b' .
            ')/i';
        if (!preg_match_all($pattern, $expr, $m)) {
            throw new \Exception('Expression tokenization failed');
        }
        foreach ($m[0] as $t) {
            $tokens[] = $t;
        }

        // Shunting-yard to RPN
        $prec = [
            '||' => 1,
            '&&' => 2,
            '==' => 3, '!=' => 3, '===' => 3, '!==' => 3, '<' => 3, '>' => 3, '<=' => 3, '>=' => 3,
            '+' => 4, '-' => 4,
            '*' => 5, '/' => 5, '%' => 5,
        ];
        $output = [];
        $stack = [];
        foreach ($tokens as $tok) {
            $t = $tok;
            if (preg_match('/^\d+\.?\d*$/', $t)) {
                $output[] = (strpos($t, '.') !== false) ? (float)$t : (int)$t;
            } elseif (preg_match("/^'(.*)'$/s", $t, $ms)) {
                $output[] = str_replace("\\'", "'", $ms[1]);
            } elseif (preg_match('/^true$/i', $t)) {
                $output[] = true;
            } elseif (preg_match('/^false$/i', $t)) {
                $output[] = false;
            } elseif (in_array($t, array_keys($prec))) {
                while (!empty($stack) && end($stack) !== '(' && ($prec[end($stack)] ?? 0) >= ($prec[$t] ?? 0)) {
                    $output[] = array_pop($stack);
                }
                $stack[] = $t;
            } elseif ($t === '(') {
                $stack[] = $t;
            } elseif ($t === ')') {
                while (!empty($stack) && end($stack) !== '(') {
                    $output[] = array_pop($stack);
                }
                if (empty($stack)) {
                    throw new \Exception('Mismatched parentheses');
                }
                array_pop($stack);
            } else {
                throw new \Exception('Unsupported token: ' . $t);
            }
        }
        while (!empty($stack)) {
            $op = array_pop($stack);
            if ($op === '(' || $op === ')') throw new \Exception('Mismatched parentheses');
            $output[] = $op;
        }

        // Evaluate RPN
        $evalStack = [];
        foreach ($output as $item) {
            if (!is_string($item)) {
                $evalStack[] = $item;
                continue;
            }
            if (in_array($item, ['+', '-', '*', '/', '%', '==', '!=', '===', '!==', '<', '>', '<=', '>=', '&&', '||'])) {
                $b = array_pop($evalStack);
                $a = array_pop($evalStack);
                switch ($item) {
                    case '+': $res = $a + $b; break;
                    case '-': $res = $a - $b; break;
                    case '*': $res = $a * $b; break;
                    case '/': $res = ($b == 0) ? null : $a / $b; break;
                    case '%': $res = ($b == 0) ? null : $a % $b; break;
                    case '==': $res = ($a == $b); break;
                    case '!=': $res = ($a != $b); break;
                    case '===': $res = ($a === $b); break;
                    case '!==': $res = ($a !== $b); break;
                    case '<': $res = ($a < $b); break;
                    case '>': $res = ($a > $b); break;
                    case '<=': $res = ($a <= $b); break;
                    case '>=': $res = ($a >= $b); break;
                    case '&&': $res = ($a && $b); break;
                    case '||': $res = ($a || $b); break;
                    default: throw new \Exception('Unknown operator ' . $item);
                }
                $evalStack[] = $res;
            } else {
                throw new \Exception('Unexpected RPN token ' . $item);
            }
        }
        if (count($evalStack) !== 1) throw new \Exception('Invalid expression evaluation');
        return (bool) array_pop($evalStack);
    }

    public static function build_bearer_header(string $apiKey): string
    {
        $k = trim($apiKey);
        // Basic validation: avoid accidental header injection
        if (preg_match('/[\r\n]/', $k)) {
            throw new \Exception('Invalid api key');
        }
        return 'Authorization: Bearer ' . $k;
    }
}
