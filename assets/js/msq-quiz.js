jQuery(document).ready(function ($) {
  function bindQuizButtons(selector, quizId) {
    $(document).on("click", selector, function (e) {
      e.preventDefault();
      startQuiz(quizId);
    });
  }

  function startQuiz(quizId) {
    const questions = window.msq_quiz_data && window.msq_quiz_data[quizId];
    const $popup = $('.msq-popup-overlay[data-quiz-id="' + quizId + '"]');

    if (!questions || !Array.isArray(questions) || questions.length === 0) {
      alert("No quiz data found.");
      console.warn("No quiz data for ID:", quizId, questions);
      return;
    }

    // ✅ Reset popup
    $popup.find(".msq-question-section, .msq-result-section").hide().empty();
    $(".msq-print-only").remove(); // clean previous print DOM
    $popup.show();

    let current = 0;
    let score = 0;
    const results = [];

    renderQuestion();

    function renderQuestion() {
      if (current >= questions.length) {
        $(".msq-print-only").remove();

        // ✅ Hidden printable version
        let printHTML = `<div class="msq-print-only" style="display:none;">
    <h2>${msq_i18n.score}: ${score}/${questions.length}</h2>
    <div class="msq-review">`;

        results.forEach((item, i) => {
          printHTML += `
      <div class="msq-review-item">
        <strong>Q${i + 1}:</strong> ${item.question}
        <span>${msq_i18n.answer} ${String(item.selected).toUpperCase()}</span>
        <span>${msq_i18n.correct}: ${String(
            item.correct
          ).toUpperCase()}</span>`;
          if (item.description) {
            printHTML += `<small>${item.description}</small>`;
          }
          printHTML += `</div><hr />`;
        });

        printHTML += `</div></div>`;

        // ✅ Visible onscreen result
        const visibleHTML = `
    <div class="msq-final-screen">
      <h2 class="msq-final-heading">${msq_i18n.final_heading}</h2>
      <p class="msq-score-line">${msq_i18n.score}: <strong>${score}/${questions.length}</strong></p>
      <p class="msq-final-subtext">${msq_i18n.final_subtext}</p>
      <button class="msq-print-btn">${msq_i18n.print}</button>
    </div>`;

        $popup.find(".msq-question-section").hide();
        $popup.find(".msq-result-section").html(visibleHTML).show();

        $.post(
          msq_ajax.ajax_url,
          {
            action: "msq_save_quiz_result",
            quiz_id: quizId,
            score: score,
            total: questions.length,
            results: results,
          },
          function (response) {
            console.log("Quiz result saved", response);
          }
        );

        $("body").append(printHTML);

        $popup
          .find(".msq-print-btn")
          .off("click")
          .on("click", function () {
            window.print();
            setTimeout(() => {
              $(".msq-print-only").hide();
            }, 1000);
          });

        return;
      }

      const q = questions[current];
      const progressPercent = Math.round(
        ((current + 1) / questions.length) * 100
      );
      const progressText = `${msq_i18n.question} ${current + 1} of ${
        questions.length
      }`;

      const progressBarHTML = `
      <div class="msq-progress-container">
      <h3 class="quiz-title">${msq_i18n.quiz_title}</h3>
        <div class="msq-progress-text">${progressText}</div>
        <div class="msq-progress-bar"><div class="msq-progress-fill" style="width: ${progressPercent}%;"></div></div>
      </div>`;

      $popup
        .find(".msq-question-section")
        .html(
          progressBarHTML +
            `<p class="msq-title-t-or-f">${msq_i18n.title}</p>` +
            `<p class="msq-question-text">${q.text}</p>` +
            `<button class="msq-ans" data-val="true">${msq_i18n.true}</button>` +
            `<button class="msq-ans" data-val="false">${msq_i18n.false}</button>`
        )
        .show();

      $popup
        .find(".msq-ans")
        .off("click")
        .on("click", function () {
          const selected = $(this).data("val");
          if (String(selected) === String(q.answer)) score++;

          results.push({
            question: q.text,
            selected: selected,
            correct: q.answer,
            description: q.description || "",
          });

          const answerText = q.answer == "true" ? msq_i18n.true : msq_i18n.false;

          const feedback =
            `
        <div class="msq-progress-container">
        <h3 class="quiz-title">${msq_i18n.quiz_title}</h3>
          <div class="msq-progress-text">${progressText}</div>
          <div class="msq-progress-bar"><div class="msq-progress-fill" style="width: ${progressPercent}%;"></div></div>
        </div>
        <p class="ans-result">
          <strong><span class="ans-text">${msq_i18n.answer}</span> ${String(
              answerText
            ).toUpperCase()}</strong>
        </p>` + (q.description ? `<p><small>${q.description}</small></p>` : "");

          $popup
            .find(".msq-question-section")
            .html(
              feedback +
                `<button class="msq-next">${msq_i18n.next}<span class="arrow">→</span></button>`
            );

          $popup
            .find(".msq-next")
            .off("click")
            .on("click", function () {
              current++;
              renderQuestion();
            });
        });

      $popup
        .find(".msq-close-btn")
        .off("click")
        .on("click", function () {
          $popup.hide();
          $popup
            .find(".msq-question-section, .msq-result-section")
            .empty()
            .hide();
          $(".msq-print-only").remove();
        });
    }
  }

  $(".msq-start-btn").click(function () {
    const quizId = $(this).data("quiz-id");
    startQuiz(quizId);
  });

  if (window.msq_custom_triggers) {
    window.msq_custom_triggers.forEach(function (entry) {
      bindQuizButtons(entry.selector, entry.quizId);
    });
  }
});
