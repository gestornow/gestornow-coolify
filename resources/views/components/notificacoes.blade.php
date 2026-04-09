<li class="nav-item dropdown-notifications navbar-dropdown dropdown me-3 me-xl-1" id="notificacoes-app">
  <a class="nav-link dropdown-toggle hide-arrow" href="javascript:void(0);" data-bs-toggle="dropdown" data-bs-auto-close="outside" aria-expanded="false">
    <i class="fa-solid fa-bell fa-lg"></i>
    <span class="badge bg-danger rounded-pill badge-notifications d-none" id="notificacoes-badge">0</span>
  </a>

  <ul class="dropdown-menu dropdown-menu-end py-0" style="min-width: 360px;">
    <li class="dropdown-menu-header border-bottom">
      <div class="dropdown-header d-flex align-items-center py-3">
        <h6 class="text-body mb-0 me-auto">Notificacoes</h6>
        <button type="button" class="btn btn-sm btn-outline-danger me-2" id="btn-apagar-todas-notificacoes">
          Apagar todas
        </button>
        <button type="button" class="btn btn-sm btn-outline-primary" id="btn-marcar-todas-lidas">
          Marcar todas como lidas
        </button>
      </div>
    </li>

    <li class="dropdown-notifications-list scrollable-container" style="max-height: 420px; overflow-y: auto;">
      <ul class="list-group list-group-flush" id="notificacoes-lista">
        <li class="list-group-item text-center text-muted py-3">Nenhuma notificacao encontrada.</li>
      </ul>
    </li>
  </ul>
</li>

<script>
  (function iniciarQuandoPronto() {
    if (!window.jQuery) {
      setTimeout(iniciarQuandoPronto, 150);
      return;
    }

    var $ = window.jQuery;
    var $app = $('#notificacoes-app');
    if (!$app.length) {
      return;
    }

    var urls = {
      listar: '/notificacoes',
      count: '/notificacoes/count',
      marcarLida: function (id) { return '/notificacoes/' + id + '/lida'; },
      marcarTodas: '/notificacoes/todas-lidas',
      apagar: function (id) { return '/notificacoes/' + id + '/apagar'; },
      apagarTodas: '/notificacoes/todas-apagadas'
    };

    var $badge = $('#notificacoes-badge');
    var $lista = $('#notificacoes-lista');
    var $dropdownToggle = $app.find('[data-bs-toggle="dropdown"]');
    var carregouLista = false;

    function csrfToken() {
      return $('meta[name="csrf-token"]').attr('content');
    }

    function iconClass(icone) {
      var nome = (icone || 'bell').toString().trim();
      if (nome.indexOf('fa-') !== 0) {
        nome = 'fa-' + nome;
      }
      return 'fa-solid ' + nome;
    }

    function resumirMensagem(texto, limite) {
      var valor = (texto || '').toString().trim();
      if (valor.length <= limite) {
        return valor;
      }
      return valor.substring(0, limite).trimEnd() + '...';
    }

    function tempoRelativo(data) {
      if (!window.moment || !data) {
        return '';
      }
      return moment(data).locale('pt-br').fromNow();
    }

    function atualizarBadge(total) {
      var valor = parseInt(total || 0, 10);
      $badge.text(valor);
      $badge.toggleClass('d-none', valor <= 0);
    }

    function normalizarLink(link) {
      var valor = (link || '').toString().trim();
      if (!valor) {
        return 'javascript:void(0);';
      }

      // Compatibilidade com notificacoes antigas salvas sem /edit.
      if (/^\/financeiro\/contas-a-pagar\/\d+$/.test(valor)) {
        return valor + '/edit';
      }

      if (/^\/financeiro\/contas-a-receber\/\d+$/.test(valor)) {
        return valor + '/edit';
      }

      return valor;
    }

    function montarItem(notificacao) {
      var naoLida = !notificacao.lida_em;
      var cor = (notificacao.cor || 'warning').toString();
      var href = normalizarLink(notificacao.link);
      var titulo = $('<div/>').text(notificacao.titulo || '').html();
      var mensagem = $('<div/>').text(resumirMensagem(notificacao.mensagem || '', 60)).html();
      var icon = iconClass(notificacao.icone);

      return '' +
        '<li class="list-group-item list-group-item-action ' + (naoLida ? 'bg-light' : '') + '">' +
          '<div class="d-flex align-items-start">' +
            '<a href="' + href + '" class="text-body d-flex text-decoration-none notificacao-item flex-grow-1" data-id="' + notificacao.id + '" data-link="' + href + '">' +
              '<div class="me-3 mt-1"><i class="' + icon + ' text-' + cor + '"></i></div>' +
              '<div class="flex-grow-1 overflow-hidden">' +
                '<div class="fw-semibold text-truncate">' + titulo + '</div>' +
                '<small class="text-muted d-block text-truncate">' + mensagem + '</small>' +
                '<small class="text-muted">' + tempoRelativo(notificacao.created_at) + '</small>' +
              '</div>' +
            '</a>' +
            '<button type="button" class="btn btn-sm btn-text-danger ms-2 btn-apagar-notificacao" data-id="' + notificacao.id + '" title="Apagar notificacao">' +
              '<i class="fa-solid fa-trash"></i>' +
            '</button>' +
          '</div>' +
        '</li>';
    }

    function renderLista(itens) {
      if (!Array.isArray(itens) || itens.length === 0) {
        $lista.html('<li class="list-group-item text-center text-muted py-3">Nenhuma notificacao encontrada.</li>');
        return;
      }

      var html = '';
      $.each(itens, function (_, notificacao) {
        html += montarItem(notificacao);
      });

      $lista.html(html);
    }

    function carregarLista() {
      return $.ajax({
        url: urls.listar,
        method: 'GET',
        dataType: 'json',
        silent: true
      }).done(function (resposta) {
        renderLista(resposta.data || []);
        carregouLista = true;
      }).fail(function (xhr) {
        if (xhr && (xhr.status === 401 || xhr.status === 419)) {
          return;
        }

        if (window.console && window.console.warn) {
          console.warn('Falha ao carregar notificacoes.', xhr && xhr.status, xhr && xhr.responseText);
        }
      });
    }

    function carregarContador() {
      return $.ajax({
        url: urls.count,
        method: 'GET',
        dataType: 'json',
        silent: true
      }).done(function (resposta) {
        atualizarBadge(resposta.total || 0);
      }).fail(function (xhr) {
        if (xhr && (xhr.status === 401 || xhr.status === 419)) {
          return;
        }

        if (window.console && window.console.warn) {
          console.warn('Falha ao carregar contador de notificacoes.', xhr && xhr.status, xhr && xhr.responseText);
        }
      });
    }

    function marcarComoLida(id) {
      return $.ajax({
        url: urls.marcarLida(id),
        method: 'POST',
        headers: {
          'X-CSRF-TOKEN': csrfToken()
        }
      });
    }

    function marcarTodasComoLidas() {
      return $.ajax({
        url: urls.marcarTodas,
        method: 'POST',
        headers: {
          'X-CSRF-TOKEN': csrfToken()
        }
      });
    }

    function apagarNotificacao(id) {
      return $.ajax({
        url: urls.apagar(id),
        method: 'POST',
        headers: {
          'X-CSRF-TOKEN': csrfToken()
        }
      });
    }

    function apagarTodasNotificacoes() {
      return $.ajax({
        url: urls.apagarTodas,
        method: 'POST',
        headers: {
          'X-CSRF-TOKEN': csrfToken()
        }
      });
    }

    $dropdownToggle.on('show.bs.dropdown', function () {
      carregarContador();
      carregarLista();
    });

    $app.on('click', '.notificacao-item', function (event) {
      event.preventDefault();

      var $item = $(this);
      var id = parseInt($item.data('id'), 10);
      var link = normalizarLink($item.data('link'));

      marcarComoLida(id)
        .always(function () {
          if (link && link !== 'javascript:void(0);') {
            window.location.href = link;
          }
        });
    });

    $app.on('click', '.btn-apagar-notificacao', function (event) {
      event.preventDefault();
      event.stopPropagation();

      var id = parseInt($(this).data('id'), 10);

      apagarNotificacao(id)
        .done(function () {
          carregarContador();
          carregarLista();
          if (window.toastr) {
            toastr.success('Notificacao apagada com sucesso.');
          }
        })
        .fail(function () {
          if (window.toastr) {
            toastr.error('Nao foi possivel apagar a notificacao.');
          }
        });
    });

    $('#btn-marcar-todas-lidas').on('click', function () {
      marcarTodasComoLidas()
        .done(function () {
          carregarContador();
          carregarLista();
          if (window.toastr) {
            toastr.success('Todas as notificacoes foram lidas.');
          }
        })
        .fail(function () {
          if (window.toastr) {
            toastr.error('Nao foi possivel marcar todas como lidas.');
          }
        });
    });

    $('#btn-apagar-todas-notificacoes').on('click', function () {
      apagarTodasNotificacoes()
        .done(function () {
          carregarContador();
          carregarLista();
          if (window.toastr) {
            toastr.success('Todas as notificacoes foram apagadas.');
          }
        })
        .fail(function () {
          if (window.toastr) {
            toastr.error('Nao foi possivel apagar todas as notificacoes.');
          }
        });
    });

    // Polling apenas do contador para reduzir carga.
    setInterval(function () {
      carregarContador();
      if (!carregouLista) {
        return;
      }
    }, 60000);

    carregarContador();
  })();
</script>