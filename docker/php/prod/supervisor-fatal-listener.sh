#!/bin/sh
# Event listener do supervisord: se o Octane entrar em FATAL (estourou o
# startretries no boot), derruba o supervisord. Sem isto o supervisord seguia
# vivo sem o Octane — container "running", porta 8000 recusando conexão, e o
# Docker não reinicia container só por estar unhealthy. Saindo o PID 1, o
# `restart: always` do compose sobe o container de novo, com backoff.
#
# Protocolo (http://supervisord.org/events.html): stdout é do supervisord —
# log só em stderr.

while :; do
    printf 'READY\n'

    read -r header || exit 0
    len=$(printf '%s\n' "$header" | sed -n 's/.*len:\([0-9]*\).*/\1/p')
    payload=$(head -c "${len:-0}")

    case "$header" in
        *eventname:PROCESS_STATE_FATAL*)
            case "$payload" in
                processname:octane\ *)
                    echo "octane em FATAL; encerrando o supervisord para o Docker reiniciar o container" >&2
                    kill -TERM "$(cat /tmp/supervisord.pid)"
                    ;;
            esac
            ;;
    esac

    printf 'RESULT 2\nOK'
done
