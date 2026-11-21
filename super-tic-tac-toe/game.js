/* File Name: game.js | Revision: 1.3.0 | Modified: 2026-11-21 */
(() => {
  'use strict';
  const root = document.querySelector('.app-shell');
  const $ = (id) => document.getElementById(id);
  const settings = { mode: 'super', players: 'computer', human: 'X', humanChoice: 'first', secondName: '', difficulty: 'medium', size: 3 };
  const sessionScore = { X: 0, O: 0 };
  let state;
  let thinking = false;
  let computerTimer = 0;
  let undoStack = [];
  let redoStack = [];
  let gameId = '';
  let completedResult = null;
  let historyQueue = Promise.resolve();
  let configurationBlocked = false;
  const savedHistory = root.dataset.saveHistory === 'true';

  function blankGame() {
    if (settings.mode === 'super') return { turn: 'X', moves: 0, last: null, target: null, mini: Array.from({ length: 9 }, () => ({ cells: Array(9).fill(''), winner: '', winningCells: [] })), winner: '', winningCells: [], winningBoards: [], over: false };
    const n = settings.size;
    return { turn: 'X', moves: 0, last: null, cells: Array(n * n).fill(''), winner: '', winningCells: [], over: false };
  }
  function winningLine(cells, n, k, expectedMark = '') {
    for (let r = 0; r < n; r++) for (let c = 0; c < n; c++) {
      const mark = cells[r * n + c]; if (!mark) continue;
      if (expectedMark && mark !== expectedMark) continue;
      for (const [dr, dc] of [[0, 1], [1, 0], [1, 1], [1, -1]]) {
        const positions = [r * n + c];
        for (let step = 1; step < k; step++) {
          const rr = r + dr * step, cc = c + dc * step;
          if (rr < 0 || rr >= n || cc < 0 || cc >= n || cells[rr * n + cc] !== mark) break;
          positions.push(rr * n + cc);
        }
        if (positions.length === k) return { mark, cells: positions };
      }
    }
    return null;
  }
  function wins(cells, n, k) {
    return winningLine(cells, n, k)?.mark || '';
  }
  function winningCellsFor(cells, n, k, mark) {
    const winning = new Set();
    for (let r = 0; r < n; r++) for (let c = 0; c < n; c++) {
      if (cells[r * n + c] !== mark) continue;
      for (const [dr, dc] of [[0, 1], [1, 0], [1, 1], [1, -1]]) {
        const line = [r * n + c];
        for (let step = 1; step < k; step++) {
          const rr = r + dr * step, cc = c + dc * step;
          if (rr < 0 || rr >= n || cc < 0 || cc >= n || cells[rr * n + cc] !== mark) break;
          line.push(rr * n + cc);
        }
        if (line.length === k) line.forEach(index => winning.add(index));
      }
    }
    return [...winning];
  }
  function legalSuperBoards() {
    const open = state.mini.map((b, i) => b.winner || b.cells.every(Boolean) ? -1 : i).filter(i => i >= 0);
    if (state.target !== null && open.includes(state.target)) return [state.target];
    return open;
  }
  function legalMoves() {
    if (settings.mode === 'classic') return state.cells.map((v, i) => v ? -1 : i).filter(i => i >= 0);
    return legalSuperBoards().flatMap(bi => state.mini[bi].cells.map((v, ci) => v ? null : [bi, ci]).filter(Boolean));
  }
  function activeWinner() {
    if (settings.mode === 'classic') return wins(state.cells, settings.size, settings.size);
    return wins(state.mini.map(b => b.winner || ''), 3, 3);
  }
  function setMark(move, mark) {
    undoStack.push(snapshot());
    redoStack = [];
    state.lastMark = mark;
    if (settings.mode === 'classic') {
      state.cells[move] = mark; state.last = { cell: move };
      state.winner = wins(state.cells, settings.size, settings.size);
      if (state.winner) state.winningCells = winningCellsFor(state.cells, settings.size, settings.size, state.winner);
    } else {
      const [bi, ci] = move; const board = state.mini[bi];
      board.cells[ci] = mark; board.winner = wins(board.cells, 3, 3);
      if (board.winner) board.winningCells = winningCellsFor(board.cells, 3, 3, board.winner);
      state.last = { board: bi, cell: ci };
      state.target = ci;
      if (state.mini[state.target].winner || state.mini[state.target].cells.every(Boolean)) state.target = null;
      state.winner = activeWinner();
      if (state.winner) state.winningBoards = winningCellsFor(state.mini.map(b => b.winner || ''), 3, 3, state.winner);
    }
    state.moves++;
    const full = settings.mode === 'classic' ? state.cells.every(Boolean) : state.mini.every(b => b.winner || b.cells.every(Boolean));
    if (state.winner || full) finish(state.winner || 'draw');
    else state.turn = mark === 'X' ? 'O' : 'X';
  }
  function startGame() {
    window.clearTimeout(computerTimer); computerTimer = 0;
    settings.mode = $('gameMode').value;
    settings.size = Number($('boardSize').value);
    settings.difficulty = $('difficulty').value;
    settings.humanChoice = document.querySelector('[data-setting="symbol"][aria-pressed="true"]').dataset.value;
    settings.players = document.querySelector('[data-setting="players"][aria-pressed="true"]').dataset.value;
    settings.secondName = $('secondPlayerName').value.trim();
    const humanSymbol = document.querySelector('[data-setting="humanSymbol"][aria-pressed="true"]').dataset.value;
    settings.human = settings.players === 'human'
      ? humanSymbol
      : settings.humanChoice === 'second' ? 'O' : (settings.humanChoice === 'random' ? (Math.random() < 0.5 ? 'X' : 'O') : 'X');
    const missingName = settings.players === 'human' && settings.secondName === '';
    $('secondPlayerName').setAttribute('aria-invalid', missingName ? 'true' : 'false');
    $('secondPlayerError').hidden = !missingName;
    configurationBlocked = missingName;
    state = blankGame(); thinking = false; undoStack = []; redoStack = []; completedResult = null;
    $('winOverlay').hidden = true;
    gameId = (window.crypto && crypto.randomUUID) ? crypto.randomUUID() : `${Date.now()}-${Math.random().toString(16).slice(2)}`;
    if (missingName) {
      render();
      $('secondPlayerName').focus();
      return;
    }
    render(); maybeComputer();
  }
  function computerTurn() { return !configurationBlocked && settings.players === 'computer' && state.turn !== settings.human && !state.over; }

  function classicMinimax(cells, turn, depth, alpha, beta) {
    const win = wins(cells, 3, 3);
    if (win) return { score: win === 'O' ? 10 - depth : depth - 10 };
    const moves = cells.map((v, i) => v ? -1 : i).filter(i => i >= 0);
    if (!moves.length) return { score: 0 };
    let best = { score: turn === 'O' ? -Infinity : Infinity };
    for (const move of moves) {
      cells[move] = turn;
      const result = classicMinimax(cells, turn === 'X' ? 'O' : 'X', depth + 1, alpha, beta);
      cells[move] = '';
      if (turn === 'O' ? result.score > best.score : result.score < best.score) best = { move, score: result.score };
      if (turn === 'O') alpha = Math.max(alpha, best.score); else beta = Math.min(beta, best.score);
      if (beta <= alpha) break;
    }
    return best;
  }
  function classicEvaluation(cells, mark, n) {
    const other = mark === 'X' ? 'O' : 'X';
    const weights = n === 4 ? [0, 2, 20, 220, 30000] : [0, 2, 14, 100, 800, 30000];
    const scoreMark = (side) => {
      let total = 0;
      for (let r = 0; r < n; r++) for (let c = 0; c < n; c++) for (const [dr, dc] of [[0,1],[1,0],[1,1],[1,-1]]) {
        const er = r + dr * (n - 1), ec = c + dc * (n - 1);
        if (er < 0 || er >= n || ec < 0 || ec >= n) continue;
        let count = 0, blocked = false;
        for (let step = 0; step < n; step++) {
          const cell = cells[(r + dr * step) * n + c + dc * step];
          if (cell && cell !== side) blocked = true;
          if (cell === side) count++;
        }
        if (!blocked) total += weights[count];
      }
      return total;
    };
    let score = scoreMark(mark) - scoreMark(other) * 1.08;
    const center = (n - 1) / 2;
    for (let i = 0; i < cells.length; i++) if (cells[i]) {
      const distance = Math.abs(Math.floor(i / n) - center) + Math.abs(i % n - center);
      score += (cells[i] === mark ? 1 : -1) * (n - distance) * 0.35;
    }
    return score;
  }
  function orderClassicMoves(cells, moves, side, ai, n, limit = moves.length) {
    return moves.map(move => {
      cells[move] = side;
      const score = classicEvaluation(cells, ai, n);
      cells[move] = '';
      return { move, score };
    }).sort((a, b) => side === ai ? b.score - a.score : a.score - b.score).slice(0, limit).map(item => item.move);
  }
  function hardClassicValue(cells, side, ai, n, depth, alpha, beta, context) {
    const winner = wins(cells, n, n);
    if (winner) return winner === ai ? 100000 + depth : -100000 - depth;
    const empty = cells.reduce((count, cell) => count + (cell ? 0 : 1), 0);
    if (!empty) return 0;
    if (depth <= 0 || context.nodes >= context.limit) return classicEvaluation(cells, ai, n);
    context.nodes++;
    const moves = cells.map((cell, i) => cell ? -1 : i).filter(i => i >= 0);
    const candidates = orderClassicMoves(cells, moves, side, ai, n, n === 4 ? 9 : 8);
    const maximizing = side === ai;
    let best = maximizing ? -Infinity : Infinity;
    for (const move of candidates) {
      cells[move] = side;
      const value = hardClassicValue(cells, side === 'X' ? 'O' : 'X', ai, n, depth - 1, alpha, beta, context);
      cells[move] = '';
      if (maximizing) { best = Math.max(best, value); alpha = Math.max(alpha, best); }
      else { best = Math.min(best, value); beta = Math.min(beta, best); }
      if (beta <= alpha || context.nodes >= context.limit) break;
    }
    return best;
  }
  function hardClassicMove(moves) {
    const n = settings.size, ai = state.turn, cells = state.cells.slice();
    const context = { nodes: 0, limit: n === 4 ? 42000 : 26000 };
    const rootMoves = orderClassicMoves(cells, moves, ai, ai, n);
    const depth = n === 4 ? 4 : 3;
    let bestMove = rootMoves[0], bestScore = -Infinity, alpha = -Infinity;
    for (const move of rootMoves) {
      cells[move] = ai;
      const score = hardClassicValue(cells, ai === 'X' ? 'O' : 'X', ai, n, depth - 1, alpha, Infinity, context);
      cells[move] = '';
      if (score > bestScore) { bestScore = score; bestMove = move; }
      alpha = Math.max(alpha, bestScore);
      if (context.nodes >= context.limit) break;
    }
    return bestMove;
  }
  function cloneSuperPosition(position) {
    return { ...position, winningCells: (position.winningCells || []).slice(), winningBoards: (position.winningBoards || []).slice(), mini: position.mini.map(board => ({ ...board, cells: board.cells.slice(), winningCells: (board.winningCells || []).slice() })) };
  }
  function superMovesFor(position) {
    const open = position.mini.map((board, i) => board.winner || board.cells.every(Boolean) ? -1 : i).filter(i => i >= 0);
    const boards = position.target !== null && open.includes(position.target) ? [position.target] : open;
    return boards.flatMap(board => position.mini[board].cells.map((cell, i) => cell ? null : [board, i]).filter(Boolean));
  }
  function applySuperSearchMove(position, move) {
    const next = cloneSuperPosition(position);
    const [boardIndex, cellIndex] = move, mark = position.turn;
    const board = next.mini[boardIndex];
    board.cells[cellIndex] = mark;
    board.winner = wins(board.cells, 3, 3);
    if (board.winner) board.winningCells = winningCellsFor(board.cells, 3, 3, board.winner);
    next.last = { board: boardIndex, cell: cellIndex };
    next.target = cellIndex;
    if (next.mini[next.target].winner || next.mini[next.target].cells.every(Boolean)) next.target = null;
    const macro = next.mini.map(item => item.winner || '');
    next.winner = wins(macro, 3, 3);
    if (next.winner) next.winningBoards = winningCellsFor(macro, 3, 3, next.winner);
    next.moves++;
    next.over = Boolean(next.winner) || next.mini.every(item => item.winner || item.cells.every(Boolean));
    if (!next.over) next.turn = mark === 'X' ? 'O' : 'X';
    return next;
  }
  function evaluateSuper(position, ai) {
    if (position.winner) return position.winner === ai ? 100000 : -100000;
    if (position.over) return 0;
    const other = ai === 'X' ? 'O' : 'X';
    const macro = position.mini.map(board => board.winner || '');
    const macroWeights = [0, 28, 900, 100000];
    let score = linePotential(macro, 3, 3, ai, macroWeights) - linePotential(macro, 3, 3, other, macroWeights) * 1.04;
    const boardWeights = [1.2, 1, 1.2, 1, 1.65, 1, 1.2, 1, 1.2];
    const localWeights = [0, 2, 24, 600];
    position.mini.forEach((board, index) => {
      if (board.winner || board.cells.every(Boolean)) return;
      score += boardWeights[index] * (linePotential(board.cells, 3, 3, ai, localWeights) - linePotential(board.cells, 3, 3, other, localWeights) * 1.02);
    });
    if (position.target !== null) {
      const target = position.mini[position.target];
      if (!target.winner && !target.cells.every(Boolean)) {
        const side = position.turn;
        for (let i = 0; i < 9; i++) if (!target.cells[i]) {
          target.cells[i] = side;
          const immediate = wins(target.cells, 3, 3) === side;
          target.cells[i] = '';
          if (immediate) { score += side === ai ? 320 : -320; break; }
        }
      }
    }
    return score;
  }
  function linePotential(cells, n, k, mark, weights) {
    let score = 0;
    for (let r = 0; r < n; r++) for (let c = 0; c < n; c++) for (const [dr, dc] of [[0,1],[1,0],[1,1],[1,-1]]) {
      const er = r + dr * (k - 1), ec = c + dc * (k - 1);
      if (er < 0 || er >= n || ec < 0 || ec >= n) continue;
      let count = 0, blocked = false;
      for (let step = 0; step < k; step++) {
        const cell = cells[(r + dr * step) * n + c + dc * step];
        if (cell && cell !== mark) blocked = true;
        if (cell === mark) count++;
      }
      if (!blocked) score += weights[count] || 0;
    }
    return score;
  }
  function hardSuperValue(position, ai, depth, alpha, beta, context) {
    if (position.winner) return position.winner === ai ? 100000 + depth : -100000 - depth;
    if (position.over) return 0;
    if (depth <= 0 || context.nodes >= context.limit) return evaluateSuper(position, ai);
    context.nodes++;
    const side = position.turn, maximizing = side === ai;
    const moves = superMovesFor(position);
    if (!moves.length) return evaluateSuper(position, ai);
    const ordered = moves.map(move => ({ move, position: applySuperSearchMove(position, move) }))
      .sort((a, b) => maximizing ? evaluateSuper(b.position, ai) - evaluateSuper(a.position, ai) : evaluateSuper(a.position, ai) - evaluateSuper(b.position, ai))
      .slice(0, context.beam);
    let best = maximizing ? -Infinity : Infinity;
    for (const item of ordered) {
      const value = hardSuperValue(item.position, ai, depth - 1, alpha, beta, context);
      if (maximizing) { best = Math.max(best, value); alpha = Math.max(alpha, best); }
      else { best = Math.min(best, value); beta = Math.min(beta, best); }
      if (beta <= alpha || context.nodes >= context.limit) break;
    }
    return best;
  }
  function hardSuperMove(moves) {
    const ai = state.turn, position = cloneSuperPosition(state);
    const candidates = moves.map(move => ({ move, position: applySuperSearchMove(position, move) }))
      .sort((a, b) => evaluateSuper(b.position, ai) - evaluateSuper(a.position, ai)).slice(0, 12);
    const context = { nodes: 0, limit: 24000, beam: 8 };
    let bestMove = candidates[0]?.move ?? moves[0], bestScore = -Infinity, alpha = -Infinity;
    for (const item of candidates) {
      const score = hardSuperValue(item.position, ai, 3, alpha, Infinity, context);
      if (score > bestScore) { bestScore = score; bestMove = item.move; }
      alpha = Math.max(alpha, bestScore);
      if (context.nodes >= context.limit) break;
    }
    return bestMove;
  }
  function tacticalMove(moves) {
    for (const move of moves) {
      const markAt = (mark) => {
        if (settings.mode === 'classic') { state.cells[move] = mark; const result = wins(state.cells, settings.size, settings.size); state.cells[move] = ''; return result === mark; }
        const [b, c] = move, cells = state.mini[b].cells; cells[c] = mark; const local = wins(cells, 3, 3); cells[c] = ''; return local === mark;
      };
      if (markAt(state.turn)) return move;
    }
    const other = state.turn === 'X' ? 'O' : 'X';
    for (const move of moves) {
      if (settings.mode === 'classic') {
        state.cells[move] = other; const winsNext = wins(state.cells, settings.size, settings.size) === other; state.cells[move] = '';
        if (winsNext) return move;
      } else {
        const [b, c] = move, cells = state.mini[b].cells;
        cells[c] = other; const winsNext = wins(cells, 3, 3) === other; cells[c] = '';
        if (winsNext) return move;
      }
    }
    return null;
  }
  function strategicMove(moves) {
    // Score open lines without allowing an opponent's mark to occupy the same line.
    const openLineScore = (cells, n, k, mark) => {
      let score = 0;
      for (let r = 0; r < n; r++) for (let c = 0; c < n; c++) for (const [dr, dc] of [[0,1],[1,0],[1,1],[1,-1]]) {
        const endR = r + dr * (k - 1), endC = c + dc * (k - 1);
        if (endR < 0 || endR >= n || endC < 0 || endC >= n) continue;
        let mine = 0, blocked = false;
        for (let s = 0; s < k; s++) { const v = cells[(r + dr*s) * n + c + dc*s]; if (v && v !== mark) blocked = true; if (v === mark) mine++; }
        if (!blocked) score += Math.pow(5, mine);
      }
      return score;
    };
    const mark = state.turn, other = mark === 'X' ? 'O' : 'X';
    let best = null, bestScore = -Infinity;
    for (const move of moves) {
      let own = 0, opp = 0, center = 0;
      if (settings.mode === 'classic') {
        state.cells[move] = mark; own = openLineScore(state.cells, settings.size, settings.size, mark);
        state.cells[move] = other; opp = openLineScore(state.cells, settings.size, settings.size, other);
        state.cells[move] = '';
        const n = settings.size, r = Math.floor(move/n), c = move%n; center = (n-1) - Math.abs(r-(n-1)/2) - Math.abs(c-(n-1)/2);
      } else {
        const [b, c] = move; const cells = state.mini[b].cells;
        cells[c] = mark; own = openLineScore(cells, 3, 3, mark); cells[c] = other; opp = openLineScore(cells, 3, 3, other); cells[c] = '';
        const centerBonus = ([4, 10, 12, 14, 16, 22].includes(b) ? 2 : 0) + (c === 4 ? 1 : 0);
        center = centerBonus;
        // Macro-board pressure adds value to mini-boards that threaten a global line.
        const macro = state.mini.map(x => x.winner);
        macro[b] = mark; own += openLineScore(macro, 3, 3, mark) * 3;
        macro[b] = other; opp += openLineScore(macro, 3, 3, other) * 3;
      }
      const score = own - opp * 1.05 + center + Math.random() * 0.01;
      if (score > bestScore) { best = move; bestScore = score; }
    }
    return best;
  }
  function chooseMove() {
    const moves = legalMoves(); if (!moves.length) return null;
    if (settings.difficulty === 'easy') return moves[Math.floor(Math.random() * moves.length)];
    const tactical = tacticalMove(moves); if (tactical) return tactical;
    if (settings.difficulty === 'hard') {
      if (settings.mode === 'super') return hardSuperMove(moves);
      if (settings.size === 3) {
        const clone = state.cells.slice();
        if (state.turn === 'O') return classicMinimax(clone, 'O', 0, -Infinity, Infinity).move;
        // The minimax evaluator treats O as maximizing; invert mark order for X's move.
        const invert = clone.map(v => v === 'X' ? 'O' : v === 'O' ? 'X' : '');
        return classicMinimax(invert, 'O', 0, -Infinity, Infinity).move;
      }
      return hardClassicMove(moves);
    }
    return strategicMove(moves);
  }
  function maybeComputer() {
    if (!computerTurn()) return;
    thinking = true; render();
    computerTimer = window.setTimeout(() => {
      computerTimer = 0;
      if (!computerTurn()) return;
      const move = chooseMove(); thinking = false;
      if (move !== null) { setMark(move, state.turn); render(); maybeComputer(); }
    }, 420);
  }
  function handleMove(boardIndex, cellIndex) {
    if (state.over || thinking || computerTurn()) return;
    let move;
    if (settings.mode === 'classic') move = cellIndex;
    else {
      if (!legalSuperBoards().includes(boardIndex) || state.mini[boardIndex].cells[cellIndex]) return;
      move = [boardIndex, cellIndex];
    }
    setMark(move, state.turn); render(); maybeComputer();
  }
  function finish(result) {
    state.over = true;
    completedResult = result === 'draw' ? 'draw' : (result === settings.human ? 'win' : 'loss');
    if (result !== 'draw') sessionScore[result]++;
    const resultName = result === 'draw' ? 'Draw' : `${result} wins`;
    $('statusText').textContent = `${resultName} · start a new game to play again`;
    $('turnHint').textContent = result === 'draw' ? 'Even match' : `${result} takes it`;
    if (result !== 'draw') showWinOverlay(result);
    recordResult(completedResult);
  }
  function showWinOverlay(mark) {
    $('winSymbol').textContent = mark;
    $('winSymbol').className = `win-symbol ${mark.toLowerCase()}-winner`;
    $('winTitle').textContent = `${mark} wins!`;
    $('winOverlay').hidden = false;
    $('closeWinOverlay').focus();
  }
  function recordResult(result) {
    if (!savedHistory) { $('saveStatus').textContent = 'Sign in with game access to save history'; return; }
    postHistory({ csrf: root.dataset.csrf, action: 'record', gameId, result, mode: settings.mode, opponent: settings.players, size: settings.mode === 'classic' ? settings.size : 9, symbol: settings.human })
      .then(r => r.json()).then(payload => {
        if (!payload.success) throw new Error('save failed');
        showHistory(payload.data.record);
      }).catch(() => { $('saveStatus').textContent = 'Could not save history; this game is still playable'; });
  }
  function retractResult() {
    if (!savedHistory || !gameId) return;
    postHistory({ csrf: root.dataset.csrf, action: 'retract', gameId })
      .then(r => r.json()).then(payload => { if (payload.success && payload.data.record) showHistory(payload.data.record); })
      .catch(() => { $('saveStatus').textContent = 'Could not update history after undo'; });
  }
  function postHistory(body) {
    historyQueue = historyQueue.catch(() => {}).then(() => fetch('api.php', {
      method: 'POST', headers: { 'Content-Type': 'application/json', 'Accept': 'application/json' }, body: JSON.stringify(body)
    }));
    return historyQueue;
  }
  function snapshot() {
    return { state: JSON.parse(JSON.stringify(state)), score: { ...sessionScore }, completedResult };
  }
  function restoreSnapshot(item) {
    state = item.state;
    sessionScore.X = item.score.X; sessionScore.O = item.score.O;
    completedResult = item.completedResult;
  }
  function undoMove() {
    if (!undoStack.length) return;
    const wasThinking = thinking || computerTimer !== 0;
    window.clearTimeout(computerTimer); computerTimer = 0; thinking = false;
    let steps = 1;
    if (settings.players === 'computer' && !wasThinking && undoStack.length > 1) {
      if ((!state.over && state.turn === settings.human) || (state.over && state.lastMark !== settings.human)) steps = 2;
    }
    const wasComplete = state.over;
    for (let i = 0; i < steps && undoStack.length; i++) {
      redoStack.push(snapshot());
      restoreSnapshot(undoStack.pop());
    }
    if (wasComplete && !state.over) { retractResult(); $('winOverlay').hidden = true; }
    render(); if (redoStack.length === 0) maybeComputer();
  }
  function redoMove() {
    if (!redoStack.length) return;
    window.clearTimeout(computerTimer); computerTimer = 0; thinking = false;
    const next = redoStack.pop();
    const wasComplete = state.over;
    undoStack.push(snapshot());
    restoreSnapshot(next);
    if (!wasComplete && state.over && completedResult) recordResult(completedResult);
    if (state.winner) showWinOverlay(state.winner);
    render(); if (redoStack.length === 0) maybeComputer();
  }
  function showHistory(record) {
    $('saveStatus').textContent = `History · ${record.games} games · ${record.wins} wins · ${record.losses} losses · ${record.draws} draws`;
    $('historySummary').textContent = `${record.games} games · ${record.wins} wins · ${record.losses} losses · ${record.draws} draws`;
    const list = $('historyList'); list.replaceChildren();
    if (!Array.isArray(record.recent) || record.recent.length === 0) {
      const empty = document.createElement('li'); empty.className = 'history-empty'; empty.textContent = 'No completed games yet.'; list.append(empty); return;
    }
    for (const entry of record.recent) {
      const item = document.createElement('li'); item.className = `history-item result-${entry.result}`;
      const top = document.createElement('div'); top.className = 'history-item-top';
      const outcome = document.createElement('strong'); outcome.className = 'history-outcome';
      outcome.textContent = entry.result === 'win' ? 'WIN' : entry.result === 'loss' ? 'LOSS' : 'DRAW';
      const date = document.createElement('time'); date.className = 'history-date';
      const played = Date.parse(entry.playedAt || '');
      date.textContent = Number.isFinite(played) ? new Intl.DateTimeFormat(undefined, { dateStyle: 'medium', timeStyle: 'short' }).format(played) : 'Date unavailable';
      top.append(outcome, date);
      const detail = document.createElement('span'); detail.className = 'history-detail';
      const mode = entry.mode === 'super' ? 'Super Tic-Tac-Toe' : `Classic ${entry.size}×${entry.size}`;
      detail.textContent = `${mode} · ${entry.opponent === 'computer' ? 'vs computer' : 'two players'} · played as ${entry.symbol}`;
      item.append(top, detail); list.append(item);
    }
  }
  function setHistoryVisible(visible) {
    $('historyPanel').hidden = !visible;
    $('historyToggle').setAttribute('aria-expanded', visible ? 'true' : 'false');
    $('historyToggle').textContent = visible ? 'Hide score history' : 'View score history';
  }
  function loadHistory() {
    if (!savedHistory) return;
    fetch('api.php', { headers: { 'Accept': 'application/json' } })
      .then(r => r.json()).then(payload => {
        if (payload.success && payload.data.available) showHistory(payload.data.record);
      }).catch(() => { $('saveStatus').textContent = 'History could not load'; });
  }
  function updateLabels() {
    $('gameTitle').textContent = settings.mode === 'super' ? 'Super Tic-Tac-Toe' : `Classic · ${settings.size} × ${settings.size}`;
    $('modeBadge').textContent = settings.mode === 'super' ? 'SUPER · 9 BOARDS' : `CLASSIC · ${settings.size} × ${settings.size}`;
    $('boardWrap').classList.toggle('super-wrap', settings.mode === 'super');
    $('boardWrap').classList.toggle('classic-wrap', settings.mode === 'classic');
    $('sizeField').hidden = settings.mode !== 'classic';
    $('symbolField').hidden = settings.players !== 'computer';
    $('humanSymbolField').hidden = settings.players !== 'human';
    $('secondPlayerField').hidden = settings.players !== 'human';
    $('ruleTitle').textContent = settings.mode === 'super' ? 'Your move sets the next board' : 'Connect a line to win';
    $('ruleText').textContent = settings.mode === 'super' ? 'Play in a mini-board, then your opponent must play in the matching position.' : `Make ${settings.size} in a row across, down, or diagonally.`;
    $('nameX').textContent = settings.players === 'human' ? (settings.human === 'X' ? 'You' : (settings.secondName || 'Second player')) : (settings.human === 'X' ? 'You' : 'Computer');
    $('nameO').textContent = settings.players === 'human' ? (settings.human === 'O' ? 'You' : (settings.secondName || 'Second player')) : (settings.human === 'O' ? 'You' : 'Computer');
    $('winsX').textContent = sessionScore.X; $('winsO').textContent = sessionScore.O;
    $('turnHint').textContent = state.over ? 'Round complete' : `${state.turn} to move`;
  }
  function render() {
    updateLabels();
    const board = $('board'); board.replaceChildren();
    if (settings.mode === 'classic') {
      board.className = `board classic-board size-${settings.size}`;
      board.style.setProperty('--board-size', settings.size);
      state.cells.forEach((mark, i) => {
        const cell = document.createElement('button'); cell.type = 'button'; cell.className = 'cell classic-cell';
        cell.setAttribute('role', 'gridcell'); cell.setAttribute('aria-label', mark ? `Cell ${i+1}, ${mark}` : `Cell ${i+1}, empty`);
        cell.textContent = mark; if (mark) cell.classList.add(mark.toLowerCase());
        if (state.winningCells.includes(i)) cell.classList.add('winning-cell');
        if (state.last?.cell === i) cell.classList.add('previous-move');
        cell.disabled = Boolean(mark) || state.over || thinking || configurationBlocked || computerTurn(); cell.addEventListener('click', () => handleMove(0, i)); board.append(cell);
      });
      $('targetText').textContent = state.over ? 'Round complete' : 'Tap any open square';
    } else {
      board.className = 'board super-board';
      const legal = legalSuperBoards();
      state.mini.forEach((mini, bi) => {
        const miniEl = document.createElement('div'); miniEl.className = 'mini-board';
        if (legal.includes(bi) && !state.over) miniEl.classList.add('playable');
        if (state.last?.board === bi) miniEl.classList.add('previous-board');
        if (mini.winner) miniEl.classList.add(`won-${mini.winner.toLowerCase()}`);
        if (state.winningBoards.includes(bi)) miniEl.classList.add('winning-mini');
        miniEl.setAttribute('role', 'group'); miniEl.setAttribute('aria-label', `Mini-board ${bi+1}${mini.winner ? `, ${mini.winner} won` : legal.includes(bi) ? ', playable' : ', unavailable'}`);
        mini.cells.forEach((mark, ci) => {
          const cell = document.createElement('button'); cell.type = 'button'; cell.className = 'cell';
          cell.setAttribute('aria-label', `Board ${bi+1}, square ${ci+1}${mark ? `, ${mark}` : ', empty'}`);
          cell.textContent = mark; if (mark) cell.classList.add(mark.toLowerCase());
          if (mini.winningCells.includes(ci)) cell.classList.add('winning-cell');
          if (state.last?.board === bi && state.last?.cell === ci) cell.classList.add('previous-move');
          cell.disabled = !legal.includes(bi) || Boolean(mark) || state.over || thinking || configurationBlocked || computerTurn();
          cell.addEventListener('click', () => handleMove(bi, ci)); miniEl.append(cell);
        });
        if (mini.winner) { const stamp = document.createElement('span'); stamp.className = `mini-winner ${mini.winner.toLowerCase()}`; stamp.textContent = mini.winner; stamp.setAttribute('aria-hidden', 'true'); miniEl.append(stamp); }
        board.append(miniEl);
      });
      $('targetText').textContent = state.over ? 'Round complete' : state.target === null ? 'Choose any open mini-board' : `Play in mini-board ${state.target + 1}`;
    }
    const waiting = computerTurn() && thinking;
    $('turnChip').textContent = state.turn; $('turnChip').className = `turn-chip ${state.turn.toLowerCase()}-chip`;
    $('statusText').textContent = configurationBlocked ? 'Enter a name for Player 2 to start' : state.over ? $('statusText').textContent : waiting ? 'Computer is thinking…' : (settings.players === 'computer' && state.turn === settings.human ? 'Your move' : settings.players === 'computer' ? 'Computer to move' : `${state.turn === settings.human ? 'Your move' : settings.secondName + ' to move'}`);
    $('scoreX').classList.toggle('active', state.turn === 'X' && !state.over); $('scoreO').classList.toggle('active', state.turn === 'O' && !state.over);
    $('moveCount').textContent = `${state.moves} ${state.moves === 1 ? 'move' : 'moves'}`;
    if (!state.over) $('turnHint').textContent = `${state.turn} to move`;
    $('undoMove').disabled = undoStack.length === 0;
    $('redoMove').disabled = redoStack.length === 0;
    $('historyPosition').textContent = `Move ${state.moves} · ${redoStack.length} redo`;
    if (savedHistory && !state.over) $('saveStatus').textContent = 'Signed-in game history enabled';
    else if (!savedHistory && !state.over) $('saveStatus').textContent = 'Scores stay on this device';
  }

  document.querySelectorAll('[data-setting]').forEach(button => button.addEventListener('click', () => {
    const group = button.dataset.setting;
    document.querySelectorAll(`[data-setting="${group}"]`).forEach(peer => peer.setAttribute('aria-pressed', peer === button ? 'true' : 'false'));
    startGame();
  }));
  $('gameMode').addEventListener('change', startGame); $('boardSize').addEventListener('change', startGame); $('difficulty').addEventListener('change', startGame);
  $('secondPlayerName').addEventListener('change', startGame);
  $('secondPlayerName').addEventListener('keydown', event => { if (event.key === 'Enter') { event.preventDefault(); startGame(); } });
  if (savedHistory) $('historyToggle').hidden = false;
  $('historyToggle').addEventListener('click', () => setHistoryVisible($('historyPanel').hidden));
  $('historyClose').addEventListener('click', () => setHistoryVisible(false));
  $('newGame').addEventListener('click', startGame); $('newGameBottom').addEventListener('click', startGame);
  $('undoMove').addEventListener('click', undoMove); $('redoMove').addEventListener('click', redoMove);
  $('closeWinOverlay').addEventListener('click', () => { $('winOverlay').hidden = true; });
  $('overlayNewGame').addEventListener('click', () => { $('winOverlay').hidden = true; startGame(); });
  $('winOverlay').addEventListener('click', event => { if (event.target === $('winOverlay')) $('winOverlay').hidden = true; });
  $('winOverlay').addEventListener('keydown', event => { if (event.key === 'Escape') $('winOverlay').hidden = true; });
  state = blankGame(); render(); loadHistory();
})();
