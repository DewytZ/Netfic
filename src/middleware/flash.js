/**
 * Mensajes que sobreviven a una sola redirección (como session()->flash en Laravel).
 */
export function flash(req, res, next) {
    const data = req.session.flash ?? {};
    delete req.session.flash;

    res.locals.status = data.status ?? null;
    res.locals.errors = data.errors ?? [];
    res.locals.old = data.old ?? {};

    res.flash = (values) => {
        req.session.flash = { ...req.session.flash, ...values };
    };

    next();
}
