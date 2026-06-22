// Budget loan request form. Tracks the requested amount and exposes the live
// interest rate and total repayment so those figures stay out of the template.
export default function loanRequestForm(config) {
    return {
        amount: config.loanMax,
        showForm: false,
        interestRate: config.interestRate,

        get interestPercent() {
            return (this.interestRate / 100) + '%';
        },

        get repaymentTotal() {
            return '€' + Math.round(this.amount * (1 + this.interestRate / 10000)).toLocaleString('es-ES');
        },
    };
}
