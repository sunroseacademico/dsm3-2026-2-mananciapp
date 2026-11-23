(function () {
    'use strict';

    var livros = [
        ['Gênesis', 'genesis', 50], ['Êxodo', 'exodus', 40], ['Levítico', 'leviticus', 27],
        ['Números', 'numbers', 36], ['Deuteronômio', 'deuteronomy', 34], ['Josué', 'joshua', 24],
        ['Juízes', 'judges', 21], ['Rute', 'ruth', 4], ['1 Samuel', '1 samuel', 31],
        ['2 Samuel', '2 samuel', 24], ['1 Reis', '1 kings', 22], ['2 Reis', '2 kings', 25],
        ['1 Crônicas', '1 chronicles', 29], ['2 Crônicas', '2 chronicles', 36], ['Esdras', 'ezra', 10],
        ['Neemias', 'nehemiah', 13], ['Ester', 'esther', 10], ['Jó', 'job', 42],
        ['Salmos', 'psalms', 150], ['Provérbios', 'proverbs', 31], ['Eclesiastes', 'ecclesiastes', 12],
        ['Cânticos', 'song of solomon', 8], ['Isaías', 'isaiah', 66], ['Jeremias', 'jeremiah', 52],
        ['Lamentações', 'lamentations', 5], ['Ezequiel', 'ezekiel', 48], ['Daniel', 'daniel', 12],
        ['Oseias', 'hosea', 14], ['Joel', 'joel', 3], ['Amós', 'amos', 9], ['Obadias', 'obadiah', 1],
        ['Jonas', 'jonah', 4], ['Miqueias', 'micah', 7], ['Naum', 'nahum', 3], ['Habacuque', 'habakkuk', 3],
        ['Sofonias', 'zephaniah', 3], ['Ageu', 'haggai', 2], ['Zacarias', 'zechariah', 14],
        ['Malaquias', 'malachi', 4], ['Mateus', 'matthew', 28], ['Marcos', 'mark', 16],
        ['Lucas', 'luke', 24], ['João', 'john', 21], ['Atos', 'acts', 28], ['Romanos', 'romans', 16],
        ['1 Coríntios', '1 corinthians', 16], ['2 Coríntios', '2 corinthians', 13], ['Gálatas', 'galatians', 6],
        ['Efésios', 'ephesians', 6], ['Filipenses', 'philippians', 4], ['Colossenses', 'colossians', 4],
        ['1 Tessalonicenses', '1 thessalonians', 5], ['2 Tessalonicenses', '2 thessalonians', 3],
        ['1 Timóteo', '1 timothy', 6], ['2 Timóteo', '2 timothy', 4], ['Tito', 'titus', 3],
        ['Filemom', 'philemon', 1], ['Hebreus', 'hebrews', 13], ['Tiago', 'james', 5],
        ['1 Pedro', '1 peter', 5], ['2 Pedro', '2 peter', 3], ['1 João', '1 john', 5],
        ['2 João', '2 john', 1], ['3 João', '3 john', 1], ['Judas', 'jude', 1], ['Apocalipse', 'revelation', 22]
    ];
    var versoes = [
        ['almeida', 'Almeida (JFA)'],
        ['kjv', 'King James Version (English)']
    ];
    var modal = document.getElementById('bibliaModal');
    if (!modal) return;
    var livroSelect = modal.querySelector('[data-biblia-livro]');
    var capituloSelect = modal.querySelector('[data-biblia-capitulo]');
    var versaoSelect = modal.querySelector('[data-biblia-versao]');
    var texto = modal.querySelector('[data-biblia-texto]');
    var status = modal.querySelector('[data-biblia-status]');
    var favoritos = modal.querySelector('[data-biblia-favoritos]');
    var favoritosLista = modal.querySelector('[data-biblia-favoritos-lista]');
    var referencia = modal.querySelector('[data-biblia-referencia]');
    var anterior = modal.querySelector('[data-biblia-anterior]');
    var proximo = modal.querySelector('[data-biblia-proximo]');
    var indicador = modal.querySelector('[data-biblia-indicador]');
    var comparacao = modal.querySelector('[data-biblia-comparacao]');
    var comparacaoGrade = modal.querySelector('[data-biblia-comparacao-grade]');
    var atual = null;
    var storageKey = 'mananciapp-biblia-favoritos';
    var notasKey = 'mananciapp-biblia-notas';
    var painelAtivo = null;
    var versiculoAtivo = null;
    var referenciaAtual = '';

    livros.forEach(function (livro, indice) {
        livroSelect.add(new Option(livro[0], indice));
    });
    versoes.forEach(function (versao) {
        versaoSelect.add(new Option(versao[1], versao[0]));
    });
    function atualizarCapitulos() {
        var livro = livros[parseInt(livroSelect.value, 10)];
        capituloSelect.innerHTML = '';
        for (var i = 1; i <= livro[2]; i++) capituloSelect.add(new Option(String(i), i));
        atualizarNavegacao();
    }
    function atualizarNavegacao() {
        var livro = livros[parseInt(livroSelect.value, 10)];
        var capitulo = parseInt(capituloSelect.value, 10) || 1;
        anterior.disabled = parseInt(livroSelect.value, 10) === 0 && capitulo === 1;
        proximo.disabled = parseInt(livroSelect.value, 10) === livros.length - 1 && capitulo === livro[2];
        indicador.textContent = livro[0] + ' · capítulo ' + capitulo;
    }
    function selecionarReferencia(livroIndice, capitulo) {
        livroSelect.value = String(livroIndice);
        atualizarCapitulos();
        capituloSelect.value = String(capitulo);
        atualizarNavegacao();
        carregar(livros[livroIndice][1] + ' ' + capitulo);
    }
    function abrir() {
        modal.hidden = false;
        document.body.classList.add('biblia-aberta');
        atualizarCapitulos();
        carregar(livros[parseInt(livroSelect.value, 10)][1] + ' ' + capituloSelect.value);
        renderFavoritos();
    }
    function fechar() {
        modal.hidden = true;
        document.body.classList.remove('biblia-aberta');
    }
    function referenciaSelecionada() {
        var livro = livros[parseInt(livroSelect.value, 10)];
        return livro[1] + ' ' + capituloSelect.value;
    }
    function referenciaEmIngles(ref) {
        var texto = String(ref || '').trim();
        for (var i = 0; i < livros.length; i++) {
            var nome = livros[i][0];
            var escapado = nome.replace(/[.*+?^${}()|[\]\\]/g, '\\$&');
            var expressao = new RegExp('^' + escapado + '(?=\\s|$)', 'i');
            if (expressao.test(texto)) return texto.replace(expressao, livros[i][1]);
        }
        return texto;
    }
    function favoritosLer() {
        try { return JSON.parse(localStorage.getItem(storageKey) || '[]'); } catch (e) { return []; }
    }
    function renderFavoritos() {
        var lista = favoritosLer();
        favoritos.hidden = !lista.length;
        favoritosLista.innerHTML = '';
        lista.forEach(function (item, indice) {
            var li = document.createElement('li');
            li.innerHTML = '<button type="button" data-favorito="' + indice + '"><strong></strong><span></span><i class="fas fa-xmark"></i></button>';
            li.querySelector('button').dataset.referencia = item.referencia;
            li.querySelector('strong').textContent = item.referencia;
            li.querySelector('span').textContent = item.texto;
            favoritosLista.appendChild(li);
        });
    }
    function notasLer() {
        try { return JSON.parse(localStorage.getItem(notasKey) || '{}'); } catch (e) { return {}; }
    }
    function chaveVersiculo(versiculo) {
        return (versiculo.book_name || '') + ' ' + versiculo.chapter + ':' + versiculo.verse;
    }
    function marcarVersiculo(versiculo) {
        var referenciaVersiculo = chaveVersiculo(versiculo);
        var lista = favoritosLer();
        if (!lista.some(function (item) { return item.referencia === referenciaVersiculo; })) {
            lista.unshift({ referencia: referenciaVersiculo, texto: versiculo.text.trim() });
            try { localStorage.setItem(storageKey, JSON.stringify(lista.slice(0, 30))); } catch (e) {}
            renderFavoritos();
        }
    }
    function salvarNota(versiculo, nota) {
        var notas = notasLer();
        var chave = chaveVersiculo(versiculo);
        if (nota.trim() === '') delete notas[chave];
        else notas[chave] = nota.trim();
        try { localStorage.setItem(notasKey, JSON.stringify(notas)); } catch (e) {}
    }
    function fecharPainelAtivo() {
        if (!painelAtivo) return;
        painelAtivo.classList.remove('is-aberto');
        if (versiculoAtivo) versiculoAtivo.classList.remove('is-ativo');
        painelAtivo = null;
        versiculoAtivo = null;
    }
    function adicionarAcoesVersiculo(versiculo, paragrafo) {
        var chave = chaveVersiculo(versiculo);
        var notas = notasLer();
        var grupo = document.createElement('span');
        grupo.className = 'biblia-versiculo-acoes';
        var botao = document.createElement('button');
        botao.type = 'button';
        botao.className = 'biblia-versiculo-menu';
        botao.setAttribute('aria-label', 'Ações para ' + chave);
        botao.innerHTML = '<i class="fas fa-ellipsis-vertical"></i>';
        var painel = document.createElement('span');
        painel.className = 'biblia-versiculo-painel';
        var marcar = document.createElement('button');
        marcar.type = 'button';
        marcar.className = 'biblia-acao-link';
        marcar.innerHTML = '<i class="fas fa-bookmark"></i> Marcar versículo';
        marcar.addEventListener('click', function () { marcarVersiculo(versiculo); });
        var campo = document.createElement('textarea');
        campo.rows = 2;
        campo.maxLength = 1000;
        campo.placeholder = 'Escreva uma anotação…';
        campo.value = notas[chave] || '';
        var salvar = document.createElement('button');
        salvar.type = 'button';
        salvar.className = 'biblia-acao-salvar';
        salvar.textContent = 'Salvar anotação';
        salvar.addEventListener('click', function () {
            salvarNota(versiculo, campo.value);
            painel.classList.remove('is-aberto');
            if (painelAtivo === painel) {
                painelAtivo = null;
                versiculoAtivo = null;
            }
            paragrafo.classList.remove('is-ativo');
            botao.focus();
        });
        var sair = document.createElement('button');
        sair.type = 'button';
        sair.className = 'biblia-acao-sair';
        sair.innerHTML = '<i class="fas fa-xmark"></i> Sair das ações';
        sair.addEventListener('click', function () {
            fecharPainelAtivo();
            botao.focus();
        });
        painel.appendChild(marcar);
        painel.appendChild(campo);
        painel.appendChild(salvar);
        painel.appendChild(sair);
        botao.addEventListener('click', function () {
            if (painelAtivo === painel) {
                fecharPainelAtivo();
                return;
            }
            fecharPainelAtivo();
            painel.classList.add('is-aberto');
            paragrafo.classList.add('is-ativo');
            painelAtivo = painel;
            versiculoAtivo = paragrafo;
        });
        paragrafo.addEventListener('click', function (evento) {
            if (evento.target === paragrafo || evento.target.nodeName === 'SUP' || evento.target.nodeType === 3) {
                fecharPainelAtivo();
                painel.classList.add('is-aberto');
                paragrafo.classList.add('is-ativo');
                painelAtivo = painel;
                versiculoAtivo = paragrafo;
            }
        });
        grupo.appendChild(botao);
        grupo.appendChild(painel);
        paragrafo.appendChild(grupo);
    }
    function consultar(ref, versao) {
        return fetch('https://bible-api.com/' + encodeURIComponent(ref) + '?translation=' + encodeURIComponent(versao))
            .then(function (res) { if (!res.ok) throw new Error('API'); return res.json(); })
            .then(function (data) {
                if (!data.verses || !data.verses.length) throw new Error('vazio');
                return data;
            });
    }
    function carregar(ref) {
        status.textContent = 'Carregando o texto bíblico…';
        atualizarNavegacao();
        texto.innerHTML = '';
        comparacao.hidden = true;
        referenciaAtual = ref;
        consultar(ref, versaoSelect.value)
            .then(function (data) {
                atual = { referencia: data.reference, texto: data.text.trim() };
                status.textContent = data.translation_name || 'Almeida';
                var titulo = document.createElement('h3');
                titulo.textContent = data.reference;
                texto.appendChild(titulo);
                data.verses.forEach(function (versiculo) {
                    var p = document.createElement('p');
                    p.innerHTML = '<sup>' + versiculo.verse + '</sup> ';
                    p.appendChild(document.createTextNode(versiculo.text.trim()));
                    p.className = 'biblia-versiculo';
                    adicionarAcoesVersiculo(versiculo, p);
                    texto.appendChild(p);
                });
            })
            .catch(function () {
                status.textContent = 'Não foi possível consultar a Bíblia agora.';
                texto.innerHTML = '<p class="biblia-erro">Verifique sua conexão e tente novamente.</p>';
            });
    }
    function comparar() {
        var ref = referenciaAtual || referenciaSelecionada();
        if (!/:\d+/.test(ref)) {
            comparacao.hidden = false;
            comparacaoGrade.innerHTML = '<p class="biblia-erro">Para comparar versões, informe um versículo completo, como João 3:16.</p>';
            status.textContent = 'A comparação funciona por versículo.';
            referencia.focus();
            return;
        }
        status.textContent = 'Consultando as versões selecionadas…';
        comparacao.hidden = false;
        comparacaoGrade.innerHTML = '<p class="biblia-comparacao-carregando">Carregando comparativo…</p>';
        Promise.all(versoes.map(function (versao) {
            var referenciaVersao = versao[0] === 'kjv' ? referenciaEmIngles(ref) : ref;
            return consultar(referenciaVersao, versao[0]).then(function (data) {
                return { nome: versao[1], dados: data };
            }).catch(function () {
                throw new Error('A versão ' + versao[1] + ' não está disponível para esta referência.');
            });
        })).then(function (resultados) {
            comparacaoGrade.innerHTML = '';
            resultados.forEach(function (resultado) {
                var card = document.createElement('article');
                card.className = 'biblia-versao-card';
                var titulo = document.createElement('h4');
                titulo.textContent = resultado.nome;
                card.appendChild(titulo);
                resultado.dados.verses.forEach(function (versiculo) {
                    var p = document.createElement('p');
                    var numero = document.createElement('sup');
                    numero.textContent = versiculo.verse;
                    p.appendChild(numero);
                    p.appendChild(document.createTextNode(' ' + versiculo.text.trim()));
                    card.appendChild(p);
                });
                comparacaoGrade.appendChild(card);
            });
            status.textContent = 'Comparação carregada para ' + resultados[0].dados.reference + '.';
        }).catch(function (erro) {
            comparacaoGrade.innerHTML = '<p class="biblia-erro">' + erro.message + '</p>';
            status.textContent = 'Não foi possível concluir a comparação.';
        });
    }
    document.querySelectorAll('[data-biblia-abrir]').forEach(function (botao) { botao.addEventListener('click', abrir); });
    modal.querySelector('[data-biblia-fechar]').addEventListener('click', fechar);
    modal.addEventListener('click', function (e) { if (e.target === modal) fechar(); });
    document.addEventListener('keydown', function (e) { if (e.key === 'Escape' && !modal.hidden) fechar(); });
    document.addEventListener('click', function (e) {
        if (painelAtivo && !e.target.closest('.biblia-versiculo')) fecharPainelAtivo();
    });
    livroSelect.addEventListener('change', atualizarCapitulos);
    capituloSelect.addEventListener('change', atualizarNavegacao);
    modal.querySelector('[data-biblia-form]').addEventListener('submit', function (e) {
        e.preventDefault(); var livro = livros[parseInt(livroSelect.value, 10)];
        atualizarNavegacao();
        carregar(livro[1] + ' ' + capituloSelect.value);
    });
    modal.querySelector('[data-biblia-comparar]').addEventListener('click', comparar);
    modal.querySelector('[data-biblia-busca-form]').addEventListener('submit', function (e) {
        e.preventDefault(); var busca = referencia.value.trim(); if (busca) carregar(busca);
    });
    anterior.addEventListener('click', function () {
        var livro = parseInt(livroSelect.value, 10);
        var capitulo = parseInt(capituloSelect.value, 10);
        if (capitulo > 1) selecionarReferencia(livro, capitulo - 1);
        else if (livro > 0) selecionarReferencia(livro - 1, livros[livro - 1][2]);
    });
    proximo.addEventListener('click', function () {
        var livro = parseInt(livroSelect.value, 10);
        var capitulo = parseInt(capituloSelect.value, 10);
        if (capitulo < livros[livro][2]) selecionarReferencia(livro, capitulo + 1);
        else if (livro < livros.length - 1) selecionarReferencia(livro + 1, 1);
    });
    favoritosLista.addEventListener('click', function (e) {
        var botao = e.target.closest('[data-favorito]'); if (!botao) return;
        if (!e.target.closest('.fa-xmark')) { carregar(botao.dataset.referencia); return; }
        var lista = favoritosLer(); lista.splice(parseInt(botao.dataset.favorito, 10), 1);
        try { localStorage.setItem(storageKey, JSON.stringify(lista)); } catch (err) {}
        renderFavoritos();
    });
})();
